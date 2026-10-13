<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Constants\TxnStatus;

/**
 * MakePayment with TxnStatus "V" - cancellation of a sale before the Pluxee
 * day-end closing (günsonu). Only the full sale can be voided; partial
 * reversals must go through MakeRefund.
 */
class VoidRequest extends PurchaseRequest
{
    protected function txnStatus(): string
    {
        return TxnStatus::VOID;
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
        $this->validate('amount', 'rrn');

        if ($this->getExternalRrnValue() === '') {
            throw new InvalidRequestException('The externalRrn parameter (or transactionId) is required.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): VoidResponse
    {
        return $this->response = new VoidResponse($this, $data);
    }
}
