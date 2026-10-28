<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\PluxeeSodexo\Message\LoginResponse;
use Omnipay\PluxeeSodexo\Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_login_success()
    {
        $this->setMockHttpResponse('LoginWsUserSuccess.txt');

        $response = $this->createGateway()->login()->send();

        $this->assertInstanceOf(LoginResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('0', $response->getCode());
        $this->assertSame('19835DD3E3A22D0F5579F4DD3153952326D4AD0B', $response->getToken());
    }

    public function test_login_failure()
    {
        $this->setMockHttpResponse('LoginWsUserError.txt');

        $response = $this->createGateway()->login()->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('3', $response->getCode());
        $this->assertSame('Kullanici adi veya sifre hatali', $response->getMessage());
        $this->assertNull($response->getToken());
    }

    public function test_login_request_payload()
    {
        $this->setMockHttpResponse('LoginWsUserSuccess.txt');

        $this->createGateway()->login()->send();

        $body = $this->sentRequestBody(0);

        $this->assertStringContainsString('<ns:LoginWsUser>', $body);
        $this->assertStringContainsString('<ns:wsUserInfo>', $body);
        $this->assertStringContainsString('<sod:UserName>testuser</sod:UserName>', $body);
        $this->assertStringContainsString('<sod:Password>testpassword</sod:Password>', $body);
        $this->assertStringContainsString('xmlns:ns="http://www.sodexopayment.com/LoginService/2019/04"', $body);

        $request = $this->getMockedRequests()[0];

        $this->assertSame('text/xml; charset=utf-8', $request->getHeaderLine('Content-Type'));
        $this->assertSame('"http://www.sodexopayment.com/LoginService/2019/04/ILoginWS/LoginWsUser"', $request->getHeaderLine('SOAPAction'));
        $this->assertStringContainsString('uat.pluxee.com.tr/LoginWS.svc', (string) $request->getUri());
    }

    public function test_login_requires_credentials()
    {
        $this->expectException(\Omnipay\Common\Exception\InvalidRequestException::class);

        $this->createGateway(['userName' => null])->login()->send();
    }

    public function test_soap_fault_is_reported_as_invalid_response()
    {
        $this->setMockHttpResponse('SoapFault.txt');

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('SOAP fault');

        $this->createGateway()->login()->send();
    }
}
