<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\PluxeeSodexo\Message\PurchaseResponse;
use Omnipay\PluxeeSodexo\Tests\Support\ArrayLogger;
use Omnipay\PluxeeSodexo\Tests\TestCase;

class PurchaseTest extends TestCase
{
    public function test_purchase_request_data()
    {
        $request = $this->createGateway()->purchase($this->validPurchaseOptions());

        $data = $request->getData();

        $this->assertSame('OtpPayment', $data['TxnType']);
        $this->assertSame('N', $data['TxnStatus']);
        $this->assertSame('133938', $data['MerchantNo']);
        $this->assertSame('531098', $data['TerminalNo']);
        $this->assertSame('ORDER-12345', $data['ExternalRrn']);
        $this->assertSame('', $data['ServiceId']);
        $this->assertSame('25.00', $data['Amount']);
        $this->assertSame('5551234567', $data['GSM']);
        $this->assertArrayNotHasKey('Rrn', $data);

        // The OTP is RSA OAEP encrypted over UTF-16LE bytes and Base64 encoded.
        $decrypted = $this->decryptOtp($data['TxnCode']);

        $this->assertSame(mb_convert_encoding('413256', 'UTF-16LE', 'UTF-8'), $decrypted);
    }

    public function test_purchase_request_normalises_gsm()
    {
        $request = $this->createGateway()->purchase($this->validPurchaseOptions([
            'gsm' => '+90 555 123 45 67',
        ]));

        $this->assertSame('5551234567', $request->getData()['GSM']);
    }

    public function test_purchase_request_uses_transaction_id_as_external_rrn()
    {
        $request = $this->createGateway()->purchase($this->validPurchaseOptions([
            'transactionId' => 'ORDER-42',
        ]));

        $this->assertSame('ORDER-42', $request->getData()['ExternalRrn']);
    }

    public function test_purchase_request_accepts_public_key_xml()
    {
        $keys = $this->rsaKeyPair();

        $gateway = $this->createGateway(['publicKeyModulus' => '', 'publicKeyExponent' => '']);
        $gateway->setPublicKeyXml(
            '<RSAKeyValue><Modulus>' . $keys['modulus'] . '</Modulus><Exponent>' . $keys['exponent'] . '</Exponent></RSAKeyValue>'
        );

        $data = $gateway->purchase($this->validPurchaseOptions())->getData();

        $this->assertSame(
            mb_convert_encoding('413256', 'UTF-16LE', 'UTF-8'),
            $this->decryptOtp($data['TxnCode'])
        );
    }

    public function test_purchase_success()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentSuccess.txt',
        ]);

        $response = $this->createGateway()->purchase($this->validPurchaseOptions())->send();

        $this->assertInstanceOf(PurchaseResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('0', $response->getCode());
        $this->assertSame('Islem basarili', $response->getMessage());
        $this->assertSame('000010401127', $response->getTransactionReference());
        $this->assertSame('000010401127', $response->getData()['Rrn']);
        $this->assertSame('107', $response->getBatchNo());
        $this->assertSame('28', $response->getOtc());
        $this->assertSame('2000', $response->getServiceId());
        $this->assertSame('CA26149F18AB1CF5', $response->getTransactionEncId());
        $this->assertNull($response->getAvailablePoint());
        $this->assertNull($response->isSignatureValid());

        // login -> action token -> MakePayment, each using the previous token.
        $this->assertCount(3, $this->getMockedRequests());

        $this->assertStringContainsString(
            '<sod:WsToken>19835DD3E3A22D0F5579F4DD3153952326D4AD0B</sod:WsToken>',
            $this->sentRequestBody(1)
        );

        $paymentBody = $this->sentRequestBody(2);

        $this->assertStringContainsString('<ns:MakePayment>', $paymentBody);
        $this->assertStringContainsString('<sod:WsToken>A532326B867D80D0C7EB8C38F918E6C491EE59DF</sod:WsToken>', $paymentBody);
        $this->assertStringContainsString('<sod:TxnCode>', $paymentBody);
        $this->assertStringContainsString('<sod:Amount>25.00</sod:Amount>', $paymentBody);
        $this->assertStringContainsString('<sod:ExternalRrn>ORDER-12345</sod:ExternalRrn>', $paymentBody);
    }

    public function test_purchase_failure_returns_a_failed_response()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentError.txt',
        ]);

        $response = $this->createGateway()->purchase($this->validPurchaseOptions())->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('12', $response->getCode());
        $this->assertSame('Kart bakiyesi yetersiz', $response->getMessage());
        $this->assertNull($response->getTransactionReference());
    }

    public function test_purchase_refreshes_an_expired_action_token_and_retries()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentActionTokenExpired.txt',
            'CreateActionTokenSuccess2.txt',
            'MakePaymentSuccess.txt',
        ]);

        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $response = $gateway->purchase($this->validPurchaseOptions())->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertCount(5, $this->getMockedRequests());

        // The retried MakePayment is sent with the second action token.
        $this->assertStringContainsString(
            '<sod:WsToken>B637437C978E91E1D8FC9D49A029F7D5A2FF6AE0</sod:WsToken>',
            $this->sentRequestBody(4)
        );

        $this->assertTrue($logger->hasMessage('rejected the action token'));
    }

    public function test_purchase_refreshes_an_expired_ws_token_and_retries()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentWsTokenExpired.txt',
            'LoginWsUserSuccess2.txt',
            'CreateActionTokenSuccess2.txt',
            'MakePaymentSuccess.txt',
        ]);

        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $response = $gateway->purchase($this->validPurchaseOptions())->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertCount(6, $this->getMockedRequests());

        // A fresh login happened (request 4) and the new tokens were used.
        $this->assertStringContainsString('<ns:LoginWsUser>', $this->sentRequestBody(3));
        $this->assertStringContainsString(
            '<sod:WsToken>2A54698CFEFE3EF1686A055E4264A64337E5BE1C</sod:WsToken>',
            $this->sentRequestBody(4)
        );
        $this->assertStringContainsString(
            '<sod:WsToken>B637437C978E91E1D8FC9D49A029F7D5A2FF6AE0</sod:WsToken>',
            $this->sentRequestBody(5)
        );

        $this->assertTrue($logger->hasMessage('rejected the WS token'));
    }

    public function test_purchase_requires_amount()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->purchase($this->validPurchaseOptions(['amount' => null]))->send();
    }

    public function test_purchase_requires_otp()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->purchase($this->validPurchaseOptions(['otp' => null]))->send();
    }

    public function test_purchase_requires_gsm()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->purchase($this->validPurchaseOptions(['gsm' => null]))->send();
    }

    public function test_purchase_validates_gsm_format()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('10-digit Turkish mobile number');

        $this->createGateway()->purchase($this->validPurchaseOptions(['gsm' => '12345']))->send();
    }

    public function test_purchase_requires_an_external_rrn()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->purchase($this->validPurchaseOptions(['transactionId' => null]))->send();
    }

    public function test_purchase_requires_the_pluxee_public_key()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('publicKeyModulus');

        $this->createGateway([
            'publicKeyModulus' => '',
            'publicKeyExponent' => '',
        ])->purchase($this->validPurchaseOptions())->send();
    }

    public function test_purchase_verifies_the_request_signature()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentSuccess.txt',
        ]);

        $gateway = $this->createGateway(['hmacKey' => 'test-hmac-key']);

        $response = $gateway->purchase($this->validPurchaseOptions())->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isSignatureValid());
        $this->assertSame('ASFjouvfwJTmwlf7pa9QnSUeVFWAbdAp3Feg8VtzIbg=', $response->getSignature());
    }

    public function test_purchase_reports_a_signature_mismatch()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'MakePaymentSignatureMismatch.txt',
        ]);

        $logger = new ArrayLogger();

        $gateway = $this->createGateway(['hmacKey' => 'test-hmac-key']);
        $gateway->setLogger($logger);

        $response = $gateway->purchase($this->validPurchaseOptions())->send();

        // The payment itself succeeded; the signature simply does not match.
        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isSignatureValid());
        $this->assertTrue($logger->hasMessage('signature does not match'));
    }

    public function test_purchase_soap_fault_is_reported_as_invalid_response()
    {
        $this->setMockHttpResponse([
            'LoginWsUserSuccess.txt',
            'CreateActionTokenSuccess.txt',
            'SoapFault.txt',
        ]);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('SOAP fault');

        $this->createGateway()->purchase($this->validPurchaseOptions())->send();
    }

    public function test_purchase_fails_when_the_service_is_unreachable()
    {
        $this->getMockClient()->addException(new \RuntimeException('Connection timed out'));

        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        try {
            $gateway->purchase($this->validPurchaseOptions())->send();
            $this->fail('An InvalidResponseException should have been thrown.');
        } catch (InvalidResponseException $exception) {
            $this->assertStringContainsString('unreachable', $exception->getMessage());
        }

        $this->assertContains('error', $logger->levels());
    }
}
