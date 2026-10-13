<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Message\SetupMerchantVirtualPosResponse;
use Omnipay\PluxeeSodexo\Tests\TestCase;

class SetupMerchantVirtualPosTest extends TestCase
{
    public function test_setup_merchant_virtual_pos_success()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'SetupMerchantVirtualPosSuccess.txt',
        ]);

        // Only the WS credentials are needed for this operation.
        $gateway = $this->createGateway(['merchantNo' => '', 'terminalNo' => '']);

        $response = $gateway->setupMerchantVirtualPos(['vkn' => '1111111111'])->send();

        $this->assertInstanceOf(SetupMerchantVirtualPosResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('566286', $response->getMerchantNo());
        $this->assertSame('491998', $response->getTerminalNo());

        $body = $this->sentRequestBody(1);

        $this->assertStringContainsString('<ns:SetUpMerchantVirtualPos>', $body);
        $this->assertStringContainsString('<ns:setUpMerchantVirtualPosRequest>', $body);
        $this->assertStringContainsString('<sod:VKN>1111111111</sod:VKN>', $body);
        $this->assertStringContainsString('<sod:WsToken>19835DD3E3A22D0F5579F4DD3153952326D4AD0B</sod:WsToken>', $body);
    }

    public function test_setup_merchant_virtual_pos_error()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'SetupMerchantVirtualPosError.txt',
        ]);

        $response = $this->createGateway([
            'merchantNo' => '',
            'terminalNo' => '',
        ])->setupMerchantVirtualPos(['vkn' => '1111111111'])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('8', $response->getCode());
        $this->assertSame('VKN icin tanim bulunamadi', $response->getMessage());
    }

    public function test_setup_merchant_virtual_pos_requires_a_vkn()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->setupMerchantVirtualPos()->send();
    }
}
