<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Constants\TxnStatus;
use Omnipay\PluxeeSodexo\Constants\TxnType;
use Omnipay\PluxeeSodexo\Helpers\Helper;

/**
 * MakePayment with TxnStatus "N" - OTP payment from the Pluxee mobile app.
 *
 * The shopper picks "Pluxee ile Öde", enters the GSM registered with Pluxee
 * and the single-use payment code generated in the Pluxee mobile app
 * ("Ödeme yap"). Both are passed as the `gsm` and `otp` parameters.
 */
class PurchaseRequest extends RemoteAbstractRequest
{
    protected ?string $encryptedOtp = null;

    /**
     * @return array<string, string>
     *
     * @throws InvalidRequestException
     */
    public function getData(): array
    {
        $this->validateCredentials();
        $this->validateMerchant();
        $this->validateOperation();

        return $this->getPaymentInfo();
    }

    protected function txnStatus(): string
    {
        return TxnStatus::SALE;
    }

    protected function requiresOtp(): bool
    {
        return true;
    }

    /**
     * @throws InvalidRequestException
     */
    protected function validateOperation(): void
    {
        $this->validate('amount', 'otp', 'gsm');

        $gsm = Helper::normalizeGsm((string) $this->getGsm());

        if ($gsm === null || preg_match('/^5\d{9}$/', $gsm) !== 1) {
            throw new InvalidRequestException('The gsm parameter must be a 10-digit Turkish mobile number (5XXXXXXXXX).');
        }

        if ($this->getExternalRrnValue() === '') {
            throw new InvalidRequestException('The externalRrn parameter (or transactionId) is required.');
        }
    }

    /**
     * @return array<string, string>
     *
     * @throws InvalidRequestException
     */
    protected function getPaymentInfo(): array
    {
        $paymentInfo = [
            'TxnType' => TxnType::OTP_PAYMENT,
            'TxnStatus' => $this->txnStatus(),
            'MerchantNo' => (string) $this->getMerchantNo(),
            'TerminalNo' => (string) $this->getTerminalNo(),
            'ExternalRrn' => $this->getExternalRrnValue(),
            'ServiceId' => '',
            'Amount' => Helper::formatAmount($this->getAmount()),
        ];

        if ($this->requiresOtp()) {
            $paymentInfo['TxnCode'] = $this->getEncryptedOtp();
            $paymentInfo['GSM'] = (string) Helper::normalizeGsm((string) $this->getGsm());
        }

        if ($this->getRrn() !== null && $this->getRrn() !== '') {
            $paymentInfo['Rrn'] = (string) $this->getRrn();
        }

        if ($this->getExternalInfo() !== null && $this->getExternalInfo() !== '') {
            $paymentInfo['ExternalInfo'] = (string) $this->getExternalInfo();
        }

        return $paymentInfo;
    }

    /**
     * @throws InvalidRequestException
     */
    protected function getEncryptedOtp(): string
    {
        if ($this->encryptedOtp !== null) {
            return $this->encryptedOtp;
        }

        $modulus = $this->getPublicKeyModulus();
        $exponent = $this->getPublicKeyExponent();

        if (($modulus === null || $modulus === '' || $exponent === null || $exponent === '') && $this->getPublicKeyXml() !== null) {
            [$modulus, $exponent] = Helper::parsePublicKeyXml((string) $this->getPublicKeyXml());
        }

        if ($modulus === null || $modulus === '' || $exponent === null || $exponent === '') {
            throw new InvalidRequestException(
                'OTP encryption requires the publicKeyModulus and publicKeyExponent parameters (or publicKeyXml) shared by Pluxee.'
            );
        }

        return $this->encryptedOtp = Helper::encryptOtp((string) $this->getOtp(), (string) $modulus, (string) $exponent);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendData($data): PurchaseResponse
    {
        $result = $this->callPaymentService('MakePayment', 'paymentInfo', $data);
        $result = $this->verifyResponseSignature($result);

        return $this->createResponse($result);
    }

    /**
     * Verify the optional HMACSHA256 signature ("RequestSignature") Pluxee
     * adds to MakePayment responses when the echo feature is active.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function verifyResponseSignature(array $result): array
    {
        $signature = $this->resultValue($result, 'RequestSignature');
        $hmacKey = $this->getHmacKey();

        if ($signature === null || $hmacKey === null || $hmacKey === '') {
            return $result;
        }

        $externalRrn = $this->getExternalRrnValue();
        $valid = Helper::verifySignature($externalRrn, $signature, (string) $hmacKey);

        if (! $valid) {
            $this->getLogger()->warning('Pluxee MakePayment response signature does not match.', [
                'externalRrn' => $externalRrn,
            ]);
        }

        $result['SignatureValid'] = $valid;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): PurchaseResponse
    {
        return $this->response = new PurchaseResponse($this, $data);
    }
}
