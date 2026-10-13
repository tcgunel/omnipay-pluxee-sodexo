<?php

namespace Omnipay\PluxeeSodexo\Message;

/**
 * MakeRefund response.
 */
class RefundResponse extends RemoteAbstractResponse
{
    /**
     * Pluxee refund transaction number (RRN).
     */
    public function getTransactionReference(): ?string
    {
        return $this->value('Rrn');
    }

    public function getBatchNo(): ?string
    {
        return $this->value('BatchNo');
    }
}
