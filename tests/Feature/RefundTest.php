<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Message\RefundResponse;
use Omnipay\PluxeeSodexo\Tests\TestCase;

class RefundTest extends TestCase
{
    public function test_refund_request_data()
    {
        $data = $this->createGateway()->refund([
            'amount' => '10.00',
            'originalRrn' => '000010401127',
            'externalRrn' => 'ORDER-REFUND-1',
        ])->getData();

        $this->assertSame('N', $data['TxnStatus']);
        $this->assertSame('133938', $data['MerchantNo']);
        $this->assertSame('531098', $data['TerminalNo']);
        $this->assertSame('10.00', $data['Amount']);
        $this->assertSame('000010401127', $data['OriginalRrn']);
        $this->assertSame('ORDER-REFUND-1', $data['ExternalRrn']);
    }

    public function test_refund_accepts_transaction_reference_as_original_rrn()
    {
        $data = $this->createGateway()->refund([
            'amount' => '10.00',
            'transactionReference' => '000010401127',
        ])->getData();

        $this->assertSame('000010401127', $data['OriginalRrn']);
        $this->assertNull($data['ExternalRrn']);
    }

    public function test_refund_requires_the_original_rrn()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->refund(['amount' => '10.00'])->send();
    }

    public function test_refund_success()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakeRefundSuccess.txt',
        ]);

        $response = $this->createGateway()->refund([
            'amount' => '10.00',
            'originalRrn' => '000010401127',
        ])->send();

        $this->assertInstanceOf(RefundResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('000000000031', $response->getTransactionReference());
        $this->assertSame('12', $response->getBatchNo());

        $body = $this->sentRequestBody(2);

        $this->assertStringContainsString('<ns:MakeRefund>', $body);
        $this->assertStringContainsString('<ns:refundInfo>', $body);
        $this->assertStringContainsString('<sod:TxnStatus>N</sod:TxnStatus>', $body);
        $this->assertStringContainsString('<sod:OriginalRrn>000010401127</sod:OriginalRrn>', $body);
        $this->assertStringContainsString('<sod:Amount>10.00</sod:Amount>', $body);
    }

    public function test_refund_failure()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakeRefundError.txt',
        ]);

        $response = $this->createGateway()->refund([
            'amount' => '10.00',
            'originalRrn' => '000010401127',
        ])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('31', $response->getCode());
        $this->assertSame('Iade edilecek islem bulunamadi', $response->getMessage());
    }
}
