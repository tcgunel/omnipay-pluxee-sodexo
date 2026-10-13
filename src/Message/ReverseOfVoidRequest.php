<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Constants\TxnStatus;

/**
 * MakePayment with TxnStatus "RV" - technical reversal of a void that never
 * produced a response. Called with the original void's Rrn.
 */
class ReverseOfVoidRequest extends PurchaseRequest
{
    protected function txnStatus(): string
    {
        return TxnStatus::REVERSE_OF_VOID;
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
    protected function createResponse($data): ReverseOfVoidResponse
    {
        return $this->response = new ReverseOfVoidResponse($this, $data);
    }
}
