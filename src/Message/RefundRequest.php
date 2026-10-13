<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Constants\TxnStatus;
use Omnipay\PluxeeSodexo\Helpers\Helper;

/**
 * MakeRefund with TxnStatus "N" - full or partial refund of a completed sale.
 *
 * The refund is booked against the Pluxee RRN returned by the original
 * MakePayment; either `originalRrn` or omnipay's `transactionReference`
 * parameter carries it. Refunds are always booked with TxnStatus "N".
 */
class RefundRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, string|null>
     *
     * @throws InvalidRequestException
     */
    public function getData(): array
    {
        $this->validateCredentials();
        $this->validateMerchant();
        $this->validate('amount');

        $originalRrn = $this->getOriginalRrn() ?: $this->getTransactionReference();

        if (empty($originalRrn)) {
            throw new InvalidRequestException('The originalRrn parameter (or transactionReference) is required.');
        }

        return [
            'TxnStatus' => TxnStatus::SALE,
            'MerchantNo' => (string) $this->getMerchantNo(),
            'TerminalNo' => (string) $this->getTerminalNo(),
            'Amount' => Helper::formatAmount($this->getAmount()),
            'OriginalRrn' => (string) $originalRrn,
            'ExternalRrn' => $this->getExternalRrn() !== null && $this->getExternalRrn() !== ''
                ? (string) $this->getExternalRrn()
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendData($data): RefundResponse
    {
        $result = $this->callPaymentService('MakeRefund', 'refundInfo', $data);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): RefundResponse
    {
        return $this->response = new RefundResponse($this, $data);
    }
}
