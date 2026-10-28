<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\PluxeeSodexo\Constants\ActionType;
use Omnipay\PluxeeSodexo\Constants\Endpoint;
use Omnipay\PluxeeSodexo\Constants\ResultCode;
use Omnipay\PluxeeSodexo\Constants\Soap;
use Omnipay\PluxeeSodexo\Helpers\Helper;
use Omnipay\PluxeeSodexo\Traits\HasLogger;
use Omnipay\PluxeeSodexo\Traits\HasParameters;
use Psr\Log\LogLevel;
use Throwable;

/**
 * SOAP transport shared by every PluxeePay request.
 *
 * Handles the two-token flow (LoginWsUser -> CreateActionToken -> operation),
 * refreshes both tokens automatically when the service answers 5/6 or 33/34,
 * and logs every request/response through the injected PSR-3 logger.
 */
abstract class RemoteAbstractRequest extends AbstractRequest
{
    use HasLogger;
    use HasParameters;

    /**
     * Cached WS token (LoginWsUser).
     */
    protected ?string $wsToken = null;

    /**
     * Cached single-use payment token (CreateActionToken).
     */
    protected ?string $actionToken = null;

    abstract protected function createResponse($data);

    protected function validateCredentials(): void
    {
        $this->validate('userName', 'password');
    }

    protected function validateMerchant(): void
    {
        $this->validate('merchantNo', 'terminalNo');
    }

    public function getLoginEndpoint(): string
    {
        return $this->getLoginWsUrl() ?: Endpoint::login((bool) $this->getTestMode());
    }

    public function getPaymentEndpoint(): string
    {
        return $this->getPaymentWsUrl() ?: Endpoint::payment((bool) $this->getTestMode());
    }

    /**
     * ExternalRrn of the current operation. Callers may either pass a unique
     * externalRrn or rely on the omnipay transactionId.
     */
    public function getExternalRrnValue(): string
    {
        $externalRrn = $this->getExternalRrn();

        if ($externalRrn !== null && $externalRrn !== '') {
            return (string) $externalRrn;
        }

        return (string) $this->getTransactionId();
    }

    /**
     * LoginWsUser - fetch (or reuse) the WS token every operation needs.
     *
     * @throws InvalidResponseException
     */
    protected function login(): string
    {
        if ($this->wsToken !== null) {
            return $this->wsToken;
        }

        $result = $this->callLoginService('LoginWsUser', 'wsUserInfo', [
            'UserName' => $this->getUserName(),
            'Password' => $this->getPassword(),
        ], false);

        $this->assertResult($result, 'LoginWsUser');

        $token = $this->resultValue($result, 'Token');

        if ($token === null || $token === '') {
            throw new InvalidResponseException('Pluxee LoginWsUser response did not contain a token.');
        }

        $this->getLogger()->debug('Pluxee WS token acquired.');

        return $this->wsToken = $token;
    }

    /**
     * CreateActionToken - fetch (or reuse) the single-use payment token used
     * by MakePayment and MakeRefund.
     *
     * @throws InvalidResponseException
     */
    protected function createActionToken(): string
    {
        if ($this->actionToken !== null) {
            return $this->actionToken;
        }

        $result = $this->callLoginService('CreateActionToken', 'actionTokenRequest', [
            'ActionType' => ActionType::SODEXO_PAYMENT,
            'MerchantNo' => $this->getMerchantNo() ?: null,
            'TerminalNo' => $this->getTerminalNo() ?: null,
        ], true);

        $this->assertResult($result, 'CreateActionToken');

        $token = $this->resultValue($result, 'Token');

        if ($token === null || $token === '') {
            throw new InvalidResponseException('Pluxee CreateActionToken response did not contain a token.');
        }

        $this->getLogger()->debug('Pluxee action token created.');

        return $this->actionToken = $token;
    }

    /**
     * Run a PaymentWS operation against a fresh action token.
     *
     * Codes 33/34 refresh the payment token, codes 5/6 refresh the WS token;
     * in both cases the operation is retried once so the caller never sees a
     * token expiry as a failed payment.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function callPaymentService(string $operation, string $parameterElement, array $fields): array
    {
        $retries = 1;

        while (true) {
            $fields['WsToken'] = $this->createActionToken();

            $result = $this->sendSoap(
                $this->getPaymentEndpoint(),
                Soap::PAYMENT_SERVICE_NAMESPACE,
                Soap::PAYMENT_CONTRACT,
                $operation,
                $parameterElement,
                $fields,
                Soap::PAYMENT_MODEL_NAMESPACE
            );

            $code = $this->resultValue($result, 'ResultCode');

            if ($retries > 0 && ResultCode::isActionTokenExpired($code)) {
                $this->getLogger()->warning('Pluxee ' . $operation . ' rejected the action token; creating a new one and retrying.', [
                    'resultCode' => $code,
                ]);
                $this->actionToken = null;
                $retries--;
                continue;
            }

            if ($retries > 0 && ResultCode::isLoginTokenExpired($code)) {
                $this->getLogger()->warning('Pluxee ' . $operation . ' rejected the WS token; logging in again and retrying.', [
                    'resultCode' => $code,
                ]);
                $this->wsToken = null;
                $this->actionToken = null;
                $retries--;
                continue;
            }

            $this->getLogger()->log(
                (string) $code === ResultCode::SUCCESS ? LogLevel::INFO : LogLevel::WARNING,
                'Pluxee ' . $operation . ' finished.',
                [
                    'resultCode' => $code,
                    'resultMessage' => $this->resultValue($result, 'ResultMessage'),
                    'rrn' => $this->resultValue($result, 'Rrn'),
                    'externalRrn' => $this->resultValue($fields, 'ExternalRrn'),
                    'batchNo' => $this->resultValue($result, 'BatchNo'),
                ]
            );

            return $result;
        }
    }

    /**
     * Run a LoginWS operation, refreshing the WS token once when the service
     * answers 5/6.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function callLoginService(
        string $operation,
        string $parameterElement,
        array $fields,
        bool $withToken
    ): array {
        $retries = 1;

        while (true) {
            if ($withToken) {
                $fields['WsToken'] = $this->login();
            }

            $result = $this->sendSoap(
                $this->getLoginEndpoint(),
                Soap::LOGIN_SERVICE_NAMESPACE,
                Soap::LOGIN_CONTRACT,
                $operation,
                $parameterElement,
                $fields,
                Soap::LOGIN_MODEL_NAMESPACE
            );

            $code = $this->resultValue($result, 'ResultCode');

            if ($retries > 0 && $withToken && ResultCode::isLoginTokenExpired($code)) {
                $this->getLogger()->warning('Pluxee ' . $operation . ' rejected the WS token; logging in again and retrying.', [
                    'resultCode' => $code,
                ]);
                $this->wsToken = null;
                $this->actionToken = null;
                $retries--;
                continue;
            }

            return $result;
        }
    }

    /**
     * Send a SOAP 1.1 request and extract the result element.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     *
     * @throws InvalidResponseException
     */
    protected function sendSoap(
        string $endpoint,
        string $serviceNamespace,
        string $contract,
        string $operation,
        string $parameterElement,
        array $fields,
        string $modelNamespace
    ): array {
        $body = Helper::buildSoapEnvelope($serviceNamespace, $operation, $parameterElement, $fields, $modelNamespace);

        $logger = $this->getLogger();

        $logger->debug('Pluxee SOAP request: ' . $operation, [
            'endpoint' => $endpoint,
            'body' => $body,
        ]);

        try {
            $httpResponse = $this->httpClient->request(
                'POST',
                $endpoint,
                [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '"' . Soap::action($serviceNamespace, $contract, $operation) . '"',
                ],
                $body
            );
        } catch (Throwable $exception) {
            $logger->error('Pluxee SOAP transport error: ' . $operation, [
                'endpoint' => $endpoint,
                'exception' => $exception,
            ]);

            throw new InvalidResponseException('Pluxee is unreachable: ' . $exception->getMessage(), 0, $exception);
        }

        $responseBody = (string) $httpResponse->getBody();

        $logger->debug('Pluxee SOAP response: ' . $operation, [
            'status' => $httpResponse->getStatusCode(),
            'body' => $responseBody,
        ]);

        try {
            return Helper::parseSoapResponse($responseBody);
        } catch (InvalidResponseException $exception) {
            $logger->error('Pluxee SOAP response could not be parsed: ' . $operation, [
                'exception' => $exception,
                'body' => $responseBody,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     *
     * @throws InvalidResponseException
     */
    protected function assertResult(array $result, string $operation): void
    {
        $code = $this->resultValue($result, 'ResultCode');

        if ((string) $code === ResultCode::SUCCESS) {
            return;
        }

        $message = $this->resultValue($result, 'ResultMessage');

        throw new InvalidResponseException(
            'Pluxee ' . $operation . ' failed with result code ' . $code . ($message !== null ? ': ' . $message : '.')
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function resultValue(array $result, string $key): ?string
    {
        if (! array_key_exists($key, $result) || $result[$key] === null) {
            return null;
        }

        return (string) $result[$key];
    }
}
