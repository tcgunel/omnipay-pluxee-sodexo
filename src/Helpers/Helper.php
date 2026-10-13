<?php

namespace Omnipay\PluxeeSodexo\Helpers;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\PluxeeSodexo\Exceptions\OmnipayPluxeeSodexoException;

class Helper
{
    /**
     * RSA OID for rsaEncryption (1.2.840.113549.1.1.1) encoded as DER.
     */
    private const RSA_ENCRYPTION_OID = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";

    /**
     * Format an amount as an invariant decimal string ("25.00").
     *
     * @param  mixed  $amount
     */
    public static function formatAmount($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Normalise a Turkish mobile number to the 10-digit 5XXXXXXXXX format the
     * service expects. Accepts +90 5xx.., 90 5xx.., 05xx.. and 5xx.. inputs.
     */
    public static function normalizeGsm(?string $gsm): ?string
    {
        if ($gsm === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $gsm);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    public static function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Build a SOAP 1.1 envelope for a PluxeePay operation.
     *
     * The fields are wrapped in the request element ("paymentInfo",
     * "refundInfo", ...) inside the service namespace; null values are
     * omitted, empty strings are sent as empty elements.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function buildSoapEnvelope(
        string $serviceNamespace,
        string $operation,
        string $parameterElement,
        array $fields,
        string $modelNamespace
    ): string {
        $elements = '';

        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }

            $elements .= '<sod:' . $name . '>' . self::xmlEscape((string) $value) . '</sod:' . $name . '>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:ns="' . $serviceNamespace . '"'
            . ' xmlns:sod="' . $modelNamespace . '">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>'
            . '<ns:' . $operation . '>'
            . '<ns:' . $parameterElement . '>' . $elements . '</ns:' . $parameterElement . '>'
            . '</ns:' . $operation . '>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    /**
     * Extract the *Result element of a SOAP response into a flat array of
     * local element names to values (nil elements become null).
     *
     * @return array<string, mixed>
     *
     * @throws InvalidResponseException
     */
    public static function parseSoapResponse(string $xml): array
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            throw new InvalidResponseException('Pluxee returned a response that is not valid XML.');
        }

        $xpath = new DOMXPath($document);

        $faults = $xpath->query('//*[local-name()="Fault"]');

        if ($faults !== false && $faults->length > 0) {
            throw new InvalidResponseException(self::extractFaultMessage($xpath));
        }

        $results = $xpath->query('//*[substring(local-name(), string-length(local-name()) - 5) = "Result"]');

        if ($results === false || $results->length === 0) {
            throw new InvalidResponseException('Pluxee response did not contain a result element.');
        }

        $result = [];

        /** @var DOMElement $resultElement */
        $resultElement = $results->item(0);

        foreach ($resultElement->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $nil = $child->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'nil');

            $result[$child->localName] = $nil === 'true' ? null : trim($child->textContent);
        }

        return $result;
    }

    /**
     * Encrypt the 6-digit OTP exactly like the reference Java/C# samples:
     * RSA OAEP (SHA-1 with MGF1) over the UTF-16LE encoded code, Base64
     * encoded. The public key is shared by Pluxee as Base64 modulus/exponent
     * strings.
     *
     * @throws InvalidRequestException
     * @throws OmnipayPluxeeSodexoException
     */
    public static function encryptOtp(string $otp, string $modulusKey, string $exponentKey): string
    {
        $publicKey = openssl_pkey_get_public(self::publicKeyPem($modulusKey, $exponentKey));

        if ($publicKey === false) {
            throw new InvalidRequestException('Pluxee public key modulus/exponent could not be loaded.');
        }

        $plain = mb_convert_encoding($otp, 'UTF-16LE', 'UTF-8');

        if (! openssl_public_encrypt($plain, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new OmnipayPluxeeSodexoException('RSA OAEP encryption of the OTP failed.');
        }

        return base64_encode($encrypted);
    }

    /**
     * Convert the Base64 modulus/exponent pair into a PEM public key so that
     * OpenSSL can consume it. No phpseclib dependency is required.
     *
     * @throws InvalidRequestException
     */
    public static function publicKeyPem(string $modulusKey, string $exponentKey): string
    {
        $modulus = self::decodeBase64Key($modulusKey);
        $exponent = self::decodeBase64Key($exponentKey);

        if ($modulus === '' || $exponent === '') {
            throw new InvalidRequestException('Pluxee public key modulus/exponent must be Base64 encoded.');
        }

        // RSAPublicKey ::= SEQUENCE { modulus INTEGER, publicExponent INTEGER }
        $rsaPublicKey = self::derEncode(0x30, self::derInteger($modulus) . self::derInteger($exponent));

        // AlgorithmIdentifier ::= SEQUENCE { OID rsaEncryption, NULL }
        $algorithmIdentifier = self::derEncode(0x30, self::RSA_ENCRYPTION_OID . "\x05\x00");

        // SubjectPublicKeyInfo ::= SEQUENCE { AlgorithmIdentifier, BIT STRING }
        $subjectPublicKeyInfo = self::derEncode(
            0x30,
            $algorithmIdentifier . self::derEncode(0x03, "\x00" . $rsaPublicKey)
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            . '-----END PUBLIC KEY-----';
    }

    /**
     * Parse the C# RSAParameters XML Pluxee ships as "PaymentPublicKey.xml".
     *
     * @return array{0: string, 1: string} Base64 encoded modulus and exponent
     *
     * @throws InvalidRequestException
     */
    public static function parsePublicKeyXml(string $xml): array
    {
        if (str_contains($xml, '-----BEGIN')) {
            throw new InvalidRequestException('The publicKeyXml parameter must contain the RSAParameters XML, not a PEM key.');
        }

        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            throw new InvalidRequestException('The publicKeyXml parameter is not valid XML.');
        }

        $xpath = new DOMXPath($document);

        $modulus = $xpath->query('//*[local-name()="Modulus"]');
        $exponent = $xpath->query('//*[local-name()="Exponent"]');

        if ($modulus === false || $modulus->length === 0 || $exponent === false || $exponent->length === 0) {
            throw new InvalidRequestException('The publicKeyXml must contain Modulus and Exponent elements.');
        }

        return [
            self::xmlKeyValue($modulus->item(0)->textContent),
            self::xmlKeyValue($exponent->item(0)->textContent),
        ];
    }

    /**
     * Verify the optional HMACSHA256 request signature Pluxee adds to
     * MakePayment responses: Base64(HMACSHA256(ExternalRrn, hmacKey)).
     */
    public static function verifySignature(string $externalRrn, string $signature, string $hmacKey): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $externalRrn, $hmacKey, true));

        return hash_equals($expected, trim($signature));
    }

    private static function extractFaultMessage(DOMXPath $xpath): string
    {
        foreach (['faultstring', 'Text', 'faultcode'] as $name) {
            $nodes = $xpath->query('//*[local-name()="' . $name . '"]');

            if ($nodes !== false && $nodes->length > 0 && trim($nodes->item(0)->textContent) !== '') {
                return 'Pluxee returned a SOAP fault: ' . trim($nodes->item(0)->textContent);
            }
        }

        return 'Pluxee returned a SOAP fault.';
    }

    private static function xmlKeyValue(string $value): string
    {
        return preg_replace('/\s+/', '', $value) ?? '';
    }

    private static function decodeBase64Key(string $key): string
    {
        // Keys are Base64 strings shared by Pluxee; the reference samples are
        // lenient about surrounding whitespace and zero bytes (UTF-16LE
        // handling), so keep the same leniency here.
        return base64_decode($key, false);
    }

    private static function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '') {
            $bytes = "\x00";
        }

        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return self::derEncode(0x02, $bytes);
    }

    private static function derEncode(int $tag, string $value): string
    {
        return chr($tag) . self::derLength(strlen($value)) . $value;
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
