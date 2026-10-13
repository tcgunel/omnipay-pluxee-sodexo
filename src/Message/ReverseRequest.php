<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Constants\TxnStatus;

/**
 * MakePayment with TxnStatus "R" - technical reversal of a sale that never
 * produced a response (timeout, connection dropped). Called with the same
 * ExternalRrn as the original attempt: if Pluxee processed it, the sale is
 * cancelled, otherwise nothing happens.
 */
class ReverseRequest extends PurchaseRequest
{
    protected function txnStatus(): string
    {
        return TxnStatus::REVERSE;
    }

    protected function requiresOtp(): bool
    {
        return false;
    }

    /**
     * @throws InvalidRequestException
     */
    protected function validateOperation(): void
    {
        $this->validate('amount', 'externalRrn');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): ReverseResponse
    {
        return $this->response = new ReverseResponse($this, $data);
    }
}
