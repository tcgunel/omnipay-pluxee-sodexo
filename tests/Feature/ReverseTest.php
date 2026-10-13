<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Message\ReverseOfVoidResponse;
use Omnipay\PluxeeSodexo\Message\ReverseResponse;
use Omnipay\PluxeeSodexo\Tests\TestCase;

class ReverseTest extends TestCase
{
    public function test_reverse_request_data()
    {
        $data = $this->createGateway()->reverse([
            'amount' => '25.00',
            'externalRrn' => 'ORDER-REVERSE-1',
        ])->getData();

        $this->assertSame('R', $data['TxnStatus']);
        $this->assertSame('ORDER-REVERSE-1', $data['ExternalRrn']);
        $this->assertArrayNotHasKey('TxnCode', $data);
        $this->assertArrayNotHasKey('Rrn', $data);
    }

    public function test_reverse_requires_an_external_rrn()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->reverse(['amount' => '25.00'])->send();
    }

    public function test_reverse_success()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentSuccess.txt',
        ]);

        $response = $this->createGateway()->reverse([
            'amount' => '25.00',
            'externalRrn' => 'ORDER-REVERSE-1',
        ])->send();

        $this->assertInstanceOf(ReverseResponse::class, $response);
        $this->assertTrue($response->isSuccessful());

        $body = $this->sentRequestBody(2);

        $this->assertStringContainsString('<sod:TxnStatus>R</sod:TxnStatus>', $body);
        $this->assertStringContainsString('<sod:ExternalRrn>ORDER-REVERSE-1</sod:ExternalRrn>', $body);
    }

    public function test_reverse_of_void_request_data()
    {
        $data = $this->createGateway()->reverseOfVoid([
            'amount' => '25.00',
            'transactionId' => 'ORDER-RV-1',
            'rrn' => '000000000031',
        ])->getData();

        $this->assertSame('RV', $data['TxnStatus']);
        $this->assertSame('000000000031', $data['Rrn']);
        $this->assertArrayNotHasKey('TxnCode', $data);
    }

    public function test_reverse_of_void_requires_the_void_rrn()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->reverseOfVoid([
            'amount' => '25.00',
            'transactionId' => 'ORDER-RV-1',
        ])->send();
    }

    public function test_reverse_of_void_success()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentSuccess.txt',
        ]);

        $response = $this->createGateway()->reverseOfVoid([
            'amount' => '25.00',
            'transactionId' => 'ORDER-RV-1',
            'rrn' => '000000000031',
        ])->send();

        $this->assertInstanceOf(ReverseOfVoidResponse::class, $response);
        $this->assertTrue($response->isSuccessful());

        $this->assertStringContainsString('<sod:TxnStatus>RV</sod:TxnStatus>', $this->sentRequestBody(2));
    }
}
