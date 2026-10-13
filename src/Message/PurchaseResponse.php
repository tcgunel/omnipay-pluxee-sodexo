<?php

namespace Omnipay\PluxeeSodexo\Message;

/**
 * MakePayment response - carried by purchase, void, reverse and
 * reverseOfVoid (they differ only in TxnStatus, the response is identical).
 */
class PurchaseResponse extends RemoteAbstractResponse
{
    /**
     * Pluxee transaction number (RRN) - keep it, refunds and voids are booked
     * against it.
     */
    public function getTransactionReference(): ?string
    {
        return $this->value('Rrn');
    }

    public function getExternalRrn(): ?string
    {
        $request = $this->getRequest();

        return $request instanceof RemoteAbstractRequest ? $request->getExternalRrnValue() : null;
    }

    /**
     * Pluxee day-end (günsonu) batch number.
     */
    public function getBatchNo(): ?string
    {
        return $this->value('BatchNo');
    }

    public function getAvailablePoint(): ?string
    {
        return $this->value('AvailablePoint');
    }

    public function getHostBalance(): ?string
    {
        return $this->value('HostBalance');
    }

    public function getOtc(): ?string
    {
        return $this->value('Otc');
    }

    public function getServiceId(): ?string
    {
        return $this->value('ServiceId');
    }

    public function getTransactionEncId(): ?string
    {
        return $this->value('TransactionEncId');
    }

    /**
     * Raw RequestSignature returned by Pluxee (HMACSHA256 over ExternalRrn).
     */
    public function getSignature(): ?string
    {
        return $this->value('RequestSignature');
    }

    /**
     * Result of the optional HMACSHA256 echo verification.
     *
     * null  - either no hmacKey parameter was configured or Pluxee did not
     *         return a signature, so nothing could be verified,
     * true  - the signature matches,
     * false - the signature does not match; treat the response with care.
     */
    public function isSignatureValid(): ?bool
    {
        if (! array_key_exists('SignatureValid', $this->result) || $this->result['SignatureValid'] === null) {
            return null;
        }

        return (bool) $this->result['SignatureValid'];
    }
}
