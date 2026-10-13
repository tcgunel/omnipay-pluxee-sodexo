<?php

namespace Omnipay\PluxeeSodexo\Tests;

use Omnipay\PluxeeSodexo\Gateway;
use Omnipay\Tests\GatewayTestCase;

class TestCase extends GatewayTestCase
{
    /** @var Gateway */
    public $gateway;

    /**
     * @var array{modulus: string, exponent: string, privateKey: string}|null
     */
    protected static ?array $rsaKeyPair = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function createGateway(array $parameters = []): Gateway
    {
        $gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $gateway->initialize(array_merge($this->gatewayParameters(), $parameters));

        return $gateway;
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatewayParameters(): array
    {
        $keys = $this->rsaKeyPair();

        return [
            'userName' => 'testuser',
            'password' => 'testpassword',
            'merchantNo' => '133938',
            'terminalNo' => '531098',
            'publicKeyModulus' => $keys['modulus'],
            'publicKeyExponent' => $keys['exponent'],
            'testMode' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPurchaseOptions(array $overrides = []): array
    {
        return array_merge([
            'amount' => '25.00',
            'transactionId' => 'ORDER-12345',
            'gsm' => '5551234567',
            'otp' => '413256',
        ], $overrides);
    }

    /**
     * A throw-away RSA key pair; the modulus/exponent pair is what Pluxee
     * shares, the private key is used to decrypt the OTP payload in tests.
     *
     * @return array{modulus: string, exponent: string, privateKey: string}
     */
    protected function rsaKeyPair(): array
    {
        if (self::$rsaKeyPair === null) {
            $resource = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($resource, $privateKey);

            $details = openssl_pkey_get_details($resource);

            self::$rsaKeyPair = [
                'modulus' => base64_encode($details['rsa']['n']),
                'exponent' => base64_encode($details['rsa']['e']),
                'privateKey' => $privateKey,
            ];
        }

        return self::$rsaKeyPair;
    }

    /**
     * Decrypt a Base64 RSA OAEP payload with the test key pair.
     */
    protected function decryptOtp(string $encrypted): string
    {
        $keys = $this->rsaKeyPair();

        openssl_private_decrypt(
            base64_decode($encrypted),
            $decrypted,
            $keys['privateKey'],
            OPENSSL_PKCS1_OAEP_PADDING
        );

        return (string) $decrypted;
    }

    /**
     * The body of the n-th HTTP request sent through the mocked client.
     */
    protected function sentRequestBody(int $index): string
    {
        $requests = $this->getMockedRequests();

        $this->assertArrayHasKey($index, $requests);

        return (string) $requests[$index]->getBody();
    }
}
