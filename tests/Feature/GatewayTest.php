<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\PluxeeSodexo\Gateway;
use Omnipay\PluxeeSodexo\Message\CreateActionTokenRequest;
use Omnipay\PluxeeSodexo\Message\LoginRequest;
use Omnipay\PluxeeSodexo\Message\PurchaseRequest;
use Omnipay\PluxeeSodexo\Message\RefundRequest;
use Omnipay\PluxeeSodexo\Message\ReverseOfVoidRequest;
use Omnipay\PluxeeSodexo\Message\ReverseRequest;
use Omnipay\PluxeeSodexo\Message\SetupMerchantVirtualPosRequest;
use Omnipay\PluxeeSodexo\Message\VoidRequest;
use Omnipay\PluxeeSodexo\Tests\Support\ArrayLogger;
use Omnipay\PluxeeSodexo\Tests\TestCase;
use Psr\Log\NullLogger;

class GatewayTest extends TestCase
{
    public function test_name()
    {
        $this->assertSame('PluxeeSodexo', $this->gateway->getName());
    }

    public function test_default_parameters()
    {
        $defaults = $this->gateway->getDefaultParameters();

        $this->assertArrayHasKey('userName', $defaults);
        $this->assertArrayHasKey('password', $defaults);
        $this->assertArrayHasKey('merchantNo', $defaults);
        $this->assertArrayHasKey('terminalNo', $defaults);
        $this->assertArrayHasKey('publicKeyModulus', $defaults);
        $this->assertArrayHasKey('publicKeyExponent', $defaults);
        $this->assertArrayHasKey('publicKeyXml', $defaults);
        $this->assertArrayHasKey('hmacKey', $defaults);
        $this->assertArrayHasKey('testMode', $defaults);
    }

    public function test_gateway_creates_a_request_for_every_operation()
    {
        $this->assertInstanceOf(LoginRequest::class, $this->gateway->login());
        $this->assertInstanceOf(CreateActionTokenRequest::class, $this->gateway->createActionToken());
        $this->assertInstanceOf(PurchaseRequest::class, $this->gateway->purchase());
        $this->assertInstanceOf(VoidRequest::class, $this->gateway->void());
        $this->assertInstanceOf(ReverseRequest::class, $this->gateway->reverse());
        $this->assertInstanceOf(ReverseOfVoidRequest::class, $this->gateway->reverseOfVoid());
        $this->assertInstanceOf(RefundRequest::class, $this->gateway->refund());
        $this->assertInstanceOf(SetupMerchantVirtualPosRequest::class, $this->gateway->setupMerchantVirtualPos());
    }

    public function test_logger_is_forwarded_to_created_requests()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->assertSame($logger, $gateway->purchase()->getLogger());
        $this->assertSame($logger, $gateway->refund()->getLogger());
    }

    public function test_logger_can_be_injected_through_the_constructor()
    {
        $logger = new ArrayLogger();

        $gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest(), $logger);

        $this->assertSame($logger, $gateway->getLogger());
        $this->assertSame($logger, $gateway->purchase()->getLogger());
    }

    public function test_requests_default_to_a_null_logger()
    {
        $this->assertInstanceOf(NullLogger::class, $this->gateway->purchase()->getLogger());
    }

    public function test_endpoints_switch_with_test_mode()
    {
        $gateway = $this->createGateway();

        $request = $gateway->purchase();

        $this->assertStringContainsString('uat.pluxee.com.tr', $request->getLoginEndpoint());
        $this->assertStringContainsString('/LoginWS.svc', $request->getLoginEndpoint());
        $this->assertStringContainsString('uat.pluxee.com.tr', $request->getPaymentEndpoint());
        $this->assertStringContainsString('/PaymentWS.svc', $request->getPaymentEndpoint());

        $gateway->setTestMode(false);

        $this->assertSame('https://payment.partners.pluxee.com.tr/LoginWS.svc', $gateway->purchase()->getLoginEndpoint());
        $this->assertSame('https://payment.partners.pluxee.com.tr/PaymentWS.svc', $gateway->purchase()->getPaymentEndpoint());
    }

    public function test_endpoints_can_be_overridden()
    {
        $request = $this->createGateway([
            'loginWsUrl' => 'https://proxy.example.com/LoginWS.svc',
            'paymentWsUrl' => 'https://proxy.example.com/PaymentWS.svc',
        ])->purchase();

        $this->assertSame('https://proxy.example.com/LoginWS.svc', $request->getLoginEndpoint());
        $this->assertSame('https://proxy.example.com/PaymentWS.svc', $request->getPaymentEndpoint());
    }
}
