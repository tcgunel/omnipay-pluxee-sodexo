<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Message\VoidResponse;
use Omnipay\PluxeeSodexo\Tests\TestCase;

class VoidTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function voidOptions(array $overrides = []): array
    {
        return array_merge([
            'amount' => '25.00',
            'transactionId' => 'ORDER-VOID-1',
            'rrn' => '000010401127',
        ], $overrides);
    }

    public function test_void_request_data()
    {
        $data = $this->createGateway()->void($this->voidOptions())->getData();

        $this->assertSame('OtpPayment', $data['TxnType']);
        $this->assertSame('V', $data['TxnStatus']);
        $this->assertSame('000010401127', $data['Rrn']);
        $this->assertSame('25.00', $data['Amount']);
        $this->assertSame('ORDER-VOID-1', $data['ExternalRrn']);
        $this->assertArrayNotHasKey('TxnCode', $data);
        $this->assertArrayNotHasKey('GSM', $data);
    }

    public function test_void_requires_the_original_rrn()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->void($this->voidOptions(['rrn' => null]))->send();
    }

    public function test_void_success()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentSuccess.txt',
        ]);

        $response = $this->createGateway()->void($this->voidOptions())->send();

        $this->assertInstanceOf(VoidResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('000010401127', $response->getTransactionReference());

        $body = $this->sentRequestBody(2);

        $this->assertStringContainsString('<ns:MakePayment>', $body);
        $this->assertStringContainsString('<sod:TxnStatus>V</sod:TxnStatus>', $body);
        $this->assertStringContainsString('<sod:Rrn>000010401127</sod:Rrn>', $body);
    }
}
