<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\PluxeeSodexo\Message\CreateActionTokenResponse;
use Omnipay\PluxeeSodexo\Tests\TestCase;

class CreateActionTokenTest extends TestCase
{
    public function test_create_action_token_success()
    {
        $this->setMockHttpResponse(['LoginWsUserSuccess.txt', 'CreateActionTokenSuccess.txt']);

        $response = $this->createGateway()->createActionToken()->send();

        $this->assertInstanceOf(CreateActionTokenResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('A532326B867D80D0C7EB8C38F918E6C491EE59DF', $response->getToken());
    }

    public function test_create_action_token_failure()
    {
        $this->setMockHttpResponse(['LoginWsUserSuccess.txt', 'CreateActionTokenError.txt']);

        $response = $this->createGateway()->createActionToken()->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('9', $response->getCode());
        $this->assertSame('Merchant tanimli degil', $response->getMessage());
    }

    public function test_create_action_token_uses_the_login_token()
    {
        $this->setMockHttpResponse(['LoginWsUserSuccess.txt', 'CreateActionTokenSuccess.txt']);

        $this->createGateway()->createActionToken()->send();

        $this->assertCount(2, $this->getMockedRequests());

        $body = $this->sentRequestBody(1);

        $this->assertStringContainsString('<ns:CreateActionToken>', $body);
        $this->assertStringContainsString('<ns:actionTokenRequest>', $body);
        // CreateActionTokenRequest has no MerchantNo/TerminalNo members.
        $this->assertStringContainsString(
            '<ns:actionTokenRequest><sod:ActionType>SodexoPayment</sod:ActionType><sod:WsToken>19835DD3E3A22D0F5579F4DD3153952326D4AD0B</sod:WsToken></ns:actionTokenRequest>',
            $body
        );
    }

    public function test_sodexo_wallet_action_type_is_not_used()
    {
        $this->setMockHttpResponse(['LoginWsUserSuccess.txt', 'CreateActionTokenSuccess.txt']);

        $this->createGateway()->createActionToken()->send();

        $this->assertStringNotContainsString('SodexoWallet', $this->sentRequestBody(1));
        $this->assertStringNotContainsString('ExternalProfileId', $this->sentRequestBody(1));
    }
}
