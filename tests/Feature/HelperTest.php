<?php

namespace Omnipay\PluxeeSodexo\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\PluxeeSodexo\Helpers\Helper;
use Omnipay\PluxeeSodexo\Tests\TestCase;

class HelperTest extends TestCase
{
    public function test_format_amount()
    {
        $this->assertSame('25.00', Helper::formatAmount('25'));
        $this->assertSame('25.50', Helper::formatAmount(25.5));
        $this->assertSame('0.01', Helper::formatAmount('0.01'));
    }

    public function test_normalize_gsm()
    {
        $this->assertSame('5551234567', Helper::normalizeGsm('5551234567'));
        $this->assertSame('5551234567', Helper::normalizeGsm('555 123 45 67'));
        $this->assertSame('5551234567', Helper::normalizeGsm('05551234567'));
        $this->assertSame('5551234567', Helper::normalizeGsm('905551234567'));
        $this->assertSame('5551234567', Helper::normalizeGsm('+90 555 123 45 67'));
        $this->assertNull(Helper::normalizeGsm(null));
        $this->assertNull(Helper::normalizeGsm(''));
        $this->assertNull(Helper::normalizeGsm('not a number'));
    }

    public function test_encrypt_otp_is_decryptable_with_the_private_key()
    {
        $keys = $this->rsaKeyPair();

        $encrypted = Helper::encryptOtp('413256', $keys['modulus'], $keys['exponent']);

        $this->assertNotSame('', $encrypted);
        $this->assertSame(
            mb_convert_encoding('413256', 'UTF-16LE', 'UTF-8'),
            $this->decryptOtp($encrypted)
        );
    }

    public function test_public_key_pem_is_a_valid_rsa_key()
    {
        $keys = $this->rsaKeyPair();

        $pem = Helper::publicKeyPem($keys['modulus'], $keys['exponent']);

        $this->assertNotFalse(openssl_pkey_get_public($pem));
    }

    public function test_public_key_pem_rejects_empty_keys()
    {
        $this->expectException(InvalidRequestException::class);

        Helper::publicKeyPem('', '');
    }

    public function test_parse_public_key_xml()
    {
        $keys = $this->rsaKeyPair();

        $xml = '<RSAKeyValue><Modulus>' . $keys['modulus'] . '</Modulus><Exponent>' . $keys['exponent'] . '</Exponent></RSAKeyValue>';

        [$modulus, $exponent] = Helper::parsePublicKeyXml($xml);

        $this->assertSame($keys['modulus'], $modulus);
        $this->assertSame($keys['exponent'], $exponent);
    }

    public function test_parse_public_key_xml_rejects_garbage()
    {
        $this->expectException(InvalidRequestException::class);

        Helper::parsePublicKeyXml('<not-a-key/>');
    }

    public function test_build_soap_envelope()
    {
        $xml = Helper::buildSoapEnvelope(
            'http://example.com/Service/2019/04',
            'MakePayment',
            'paymentInfo',
            ['A' => 'x&y <z>', 'B' => null, 'C' => ''],
            'http://example.com/Model'
        );

        $this->assertStringContainsString('xmlns:ns="http://example.com/Service/2019/04"', $xml);
        $this->assertStringContainsString('xmlns:sod="http://example.com/Model"', $xml);
        $this->assertStringContainsString('<ns:MakePayment><ns:paymentInfo>', $xml);
        $this->assertStringContainsString('<sod:A>x&amp;y &lt;z&gt;</sod:A>', $xml);
        $this->assertStringNotContainsString('<sod:B>', $xml);
        $this->assertStringContainsString('<sod:C></sod:C>', $xml);
    }

    public function test_parse_soap_response()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
  <s:Body>
    <MakePaymentResponse xmlns="http://www.sodexopayment.com/PaymentService/2019/04">
      <MakePaymentResult xmlns:a="http://schemas.datacontract.org/2004/07/SodexoPayment.Common.Model.Payment" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
        <ResultCode xmlns="http://schemas.datacontract.org/2004/07/SodexoPayment.Common.Model">0</ResultCode>
        <ResultMessage xmlns="http://schemas.datacontract.org/2004/07/SodexoPayment.Common.Model">Islem basarili</ResultMessage>
        <a:AvailablePoint i:nil="true"/>
        <a:BatchNo>107</a:BatchNo>
        <a:Rrn>000010401127</a:Rrn>
      </MakePaymentResult>
    </MakePaymentResponse>
  </s:Body>
</s:Envelope>
XML;

        $result = Helper::parseSoapResponse($xml);

        $this->assertSame('0', $result['ResultCode']);
        $this->assertSame('Islem basarili', $result['ResultMessage']);
        $this->assertNull($result['AvailablePoint']);
        $this->assertSame('107', $result['BatchNo']);
        $this->assertSame('000010401127', $result['Rrn']);
    }

    public function test_parse_soap_fault()
    {
        $xml = file_get_contents(__DIR__ . '/../Mock/SoapFault.txt');
        $xml = substr($xml, strpos($xml, '<?xml'));

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Object reference not set to an instance of an object.');

        Helper::parseSoapResponse($xml);
    }

    public function test_parse_soap_response_without_result_element()
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('did not contain a result element');

        Helper::parseSoapResponse('<root><child>nothing</child></root>');
    }

    public function test_parse_soap_response_rejects_invalid_xml()
    {
        $this->expectException(InvalidResponseException::class);

        Helper::parseSoapResponse('<not-closed');
    }

    public function test_verify_signature()
    {
        $signature = base64_encode(hash_hmac('sha256', 'ORDER-12345', 'test-hmac-key', true));

        $this->assertTrue(Helper::verifySignature('ORDER-12345', $signature, 'test-hmac-key'));
        $this->assertFalse(Helper::verifySignature('ORDER-12345', $signature, 'another-key'));
        $this->assertFalse(Helper::verifySignature('ORDER-99999', $signature, 'test-hmac-key'));
    }
}
