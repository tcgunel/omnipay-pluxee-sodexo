<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\PluxeeSodexo\Tests\Support\ArrayLogger;
use Omnipay\PluxeeSodexo\Tests\TestCase;

class LoggerTest extends TestCase
{
    public function test_purchase_logs_the_full_soap_traffic()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentSuccess.txt',
        ]);

        $response = $gateway->purchase($this->validPurchaseOptions())->send();

        $this->assertTrue($response->isSuccessful());

        $this->assertTrue($logger->hasMessage('Pluxee SOAP request: LoginWsUser'));
        $this->assertTrue($logger->hasMessage('Pluxee SOAP response: LoginWsUser'));
        $this->assertTrue($logger->hasMessage('Pluxee SOAP request: CreateActionToken'));
        $this->assertTrue($logger->hasMessage('Pluxee SOAP request: MakePayment'));
        $this->assertTrue($logger->hasMessage('Pluxee SOAP response: MakePayment'));
        $this->assertTrue($logger->hasMessage('Pluxee WS token acquired.'));
        $this->assertTrue($logger->hasMessage('Pluxee action token created.'));

        $requestRecord = $logger->findByMessage('Pluxee SOAP request: MakePayment');

        $this->assertNotNull($requestRecord);
        $this->assertSame('debug', $requestRecord['level']);
        $this->assertStringContainsString('<ns:MakePayment>', $requestRecord['context']['body']);
        $this->assertSame('https://payment.partners.uat.pluxee.com.tr/PaymentWS.svc', $requestRecord['context']['endpoint']);

        $responseRecord = $logger->findByMessage('Pluxee SOAP response: MakePayment');

        $this->assertNotNull($responseRecord);
        $this->assertSame('debug', $responseRecord['level']);
        $this->assertStringContainsString('000010401127', $responseRecord['context']['body']);
        $this->assertSame(200, $responseRecord['context']['status']);

        $finishedRecord = $logger->findByMessage('Pluxee MakePayment finished.');

        $this->assertNotNull($finishedRecord);
        $this->assertSame('info', $finishedRecord['level']);
        $this->assertSame('0', $finishedRecord['context']['resultCode']);
        $this->assertSame('000010401127', $finishedRecord['context']['rrn']);
        $this->assertSame('ORDER-12345', $finishedRecord['context']['externalRrn']);
    }

    public function test_failed_payment_is_logged_as_warning()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentError.txt',
        ]);

        $gateway->purchase($this->validPurchaseOptions())->send();

        $record = $logger->findByMessage('Pluxee MakePayment finished.');

        $this->assertNotNull($record);
        $this->assertSame('warning', $record['level']);
        $this->assertSame('12', $record['context']['resultCode']);
        $this->assertSame('Kart bakiyesi yetersiz', $record['context']['resultMessage']);
    }

    public function test_token_refresh_is_logged_as_warning()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentActionTokenExpired.txt',
            'CreateActionTokenSuccess2.txt',
            'MakePaymentSuccess.txt',
        ]);

        $gateway->purchase($this->validPurchaseOptions())->send();

        $record = $logger->findByMessage('rejected the action token');

        $this->assertNotNull($record);
        $this->assertSame('warning', $record['level']);
        $this->assertSame('33', $record['context']['resultCode']);
    }

    public function test_soap_fault_is_logged_as_error()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'SoapFault.txt',
        ]);

        try {
            $gateway->purchase($this->validPurchaseOptions())->send();
            $this->fail('An InvalidResponseException should have been thrown.');
        } catch (InvalidResponseException $exception) {
            $this->assertStringContainsString('SOAP fault', $exception->getMessage());
        }

        $record = $logger->findByMessage('could not be parsed');

        $this->assertNotNull($record);
        $this->assertSame('error', $record['level']);
    }

    public function test_logging_is_optional()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentSuccess.txt',
        ]);

        // No logger injected: the NullLogger silently discards everything.
        $response = $this->createGateway()->purchase($this->validPurchaseOptions())->send();

        $this->assertTrue($response->isSuccessful());
    }
}
