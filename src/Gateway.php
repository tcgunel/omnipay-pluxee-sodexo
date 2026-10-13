<?php

namespace Omnipay\PluxeeSodexo;

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\PluxeeSodexo\Message\CreateActionTokenRequest;
use Omnipay\PluxeeSodexo\Message\LoginRequest;
use Omnipay\PluxeeSodexo\Message\PurchaseRequest;
use Omnipay\PluxeeSodexo\Message\RefundRequest;
use Omnipay\PluxeeSodexo\Message\RemoteAbstractRequest;
use Omnipay\PluxeeSodexo\Message\ReverseOfVoidRequest;
use Omnipay\PluxeeSodexo\Message\ReverseRequest;
use Omnipay\PluxeeSodexo\Message\SetupMerchantVirtualPosRequest;
use Omnipay\PluxeeSodexo\Message\VoidRequest;
use Omnipay\PluxeeSodexo\Traits\HasLogger;
use Omnipay\PluxeeSodexo\Traits\HasParameters;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * Pluxee (formerly Sodexo) Gateway
 *
 * Implements the PluxeePay "Online Platform / Otp" integration (document
 * v5.3): OTP payments made with the payment code generated in the Pluxee
 * mobile app, plus refund, void and the technical reverse operations.
 *
 * An optional PSR-3 logger can be injected via the constructor or
 * setLogger(); it receives the full SOAP traffic on debug level.
 *
 * (c) Tolga Can Günel
 * http://www.github.com/tcgunel/omnipay-pluxee-sodexo
 *
 * @method \Omnipay\Common\Message\NotificationInterface acceptNotification(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface authorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface completeAuthorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface fetchTransaction(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface createCard(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface updateCard(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface deleteCard(array $options = [])
 */
class Gateway extends AbstractGateway
{
    use HasLogger;
    use HasParameters;

    public function __construct(
        ?ClientInterface $httpClient = null,
        ?HttpRequest $httpRequest = null,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger;

        parent::__construct($httpClient, $httpRequest);
    }

    public function getName(): string
    {
        return 'PluxeeSodexo';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return [
            'userName' => '',
            'password' => '',
            'merchantNo' => '',
            'terminalNo' => '',
            'publicKeyModulus' => '',
            'publicKeyExponent' => '',
            'publicKeyXml' => null,
            'hmacKey' => null,
            'loginWsUrl' => null,
            'paymentWsUrl' => null,
            'testMode' => false,
        ];
    }

    /**
     * LoginWsUser - validate the WS credentials and fetch a token.
     */
    public function login(array $options = []): AbstractRequest
    {
        return $this->createRequest(LoginRequest::class, $options);
    }

    /**
     * CreateActionToken - fetch a single-use SodexoPayment token.
     */
    public function createActionToken(array $options = []): AbstractRequest
    {
        return $this->createRequest(CreateActionTokenRequest::class, $options);
    }

    /**
     * MakePayment - sale with TxnStatus "N".
     */
    public function purchase(array $options = []): AbstractRequest
    {
        return $this->createRequest(PurchaseRequest::class, $options);
    }

    /**
     * MakePayment - sale cancellation with TxnStatus "V" (before day-end).
     */
    public function void(array $options = []): AbstractRequest
    {
        return $this->createRequest(VoidRequest::class, $options);
    }

    /**
     * MakePayment - technical reversal with TxnStatus "R" of a sale whose
     * result is unknown (timeout).
     */
    public function reverse(array $options = []): AbstractRequest
    {
        return $this->createRequest(ReverseRequest::class, $options);
    }

    /**
     * MakePayment - technical reversal with TxnStatus "RV" of a void whose
     * result is unknown (timeout).
     */
    public function reverseOfVoid(array $options = []): AbstractRequest
    {
        return $this->createRequest(ReverseOfVoidRequest::class, $options);
    }

    /**
     * MakeRefund - full or partial refund of a sale.
     */
    public function refund(array $options = []): AbstractRequest
    {
        return $this->createRequest(RefundRequest::class, $options);
    }

    /**
     * SetUpMerchantVirtualPos - fetch the merchantNo / terminalNo assigned to
     * a merchant (VKN).
     */
    public function setupMerchantVirtualPos(array $options = []): AbstractRequest
    {
        return $this->createRequest(SetupMerchantVirtualPosRequest::class, $options);
    }

    /**
     * Forward the injected logger to every request the gateway creates.
     *
     * @param  string  $class
     * @param  array<string, mixed>  $parameters
     */
    protected function createRequest($class, array $parameters)
    {
        /** @var RemoteAbstractRequest $request */
        $request = parent::createRequest($class, $parameters);

        $request->setLogger($this->logger);

        return $request;
    }
}
