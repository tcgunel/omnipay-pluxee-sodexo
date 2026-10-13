# Omnipay: Pluxee (Sodexo)

**PluxeePay "Online Platform / Otp" gateway for the Omnipay PHP payment processing library**

[![Latest Stable Version](https://poser.pugx.org/tcgunel/omnipay-pluxee-sodexo/v/stable)](https://packagist.org/packages/tcgunel/omnipay-pluxee-sodexo)
[![Total Downloads](https://poser.pugx.org/tcgunel/omnipay-pluxee-sodexo/downloads)](https://packagist.org/packages/tcgunel/omnipay-pluxee-sodexo)
[![License](https://poser.pugx.org/tcgunel/omnipay-pluxee-sodexo/license)](https://packagist.org/packages/tcgunel/omnipay-pluxee-sodexo)

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway payment processing library for PHP.
This package implements the Pluxee (formerly Sodexo) meal card payment for online checkout, following the
*PluxeePay Integration v5.3 - Online Platform / Otp* contract.

The shopper pays with the payment code generated in the Pluxee mobile app:

1. The shopper picks "Pluxee ile Öde" at checkout and enters the GSM number registered with Pluxee together with the
   single-use payment code ("Ödeme yap" in the Pluxee mobile app).
2. The gateway logs in (`LoginWsUser`), gets a single-use payment token (`CreateActionToken`) and runs `MakePayment`
   with the RSA encrypted OTP.
3. Pluxee answers synchronously with a result code and the Pluxee RRN. There is no redirect and no callback: the
   result of `purchase()` **is** the payment result.

## Requirements

- PHP 8.3+ with `ext-dom`, `ext-mbstring` and `ext-openssl`
- Static IP whitelisted by Pluxee (all services are IP restricted)
- The following credentials, shared by Pluxee for the merchant:
  - `userName` / `password` (WS user, `LoginWsUser`)
  - `merchantNo` / `terminalNo` (from `SetUpMerchantVirtualPos` or the Pluxee onboarding)
  - `publicKeyModulus` / `publicKeyExponent` (the Base64 RSA public key parts used to encrypt the OTP)
  - optionally a `hmacKey` if the HMAC request signature echo feature is activated

## Installation

```bash
composer require tcgunel/omnipay-pluxee-sodexo
```

## Usage

### Gateway Initialization

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('PluxeeSodexo');

$gateway->setUserName('WSUSER');
$gateway->setPassword('WSPASSWORD');
$gateway->setMerchantNo('133938');
$gateway->setTerminalNo('531098');
$gateway->setPublicKeyModulus('...');  // Base64 modulus shared by Pluxee
$gateway->setPublicKeyExponent('...'); // Base64 exponent shared by Pluxee
$gateway->setHmacKey('...');           // optional, for signature echo verification
$gateway->setTestMode(true);           // false for production (payment.partners.pluxee.com.tr)
```

Instead of the modulus/exponent pair you can hand Pluxee's `PaymentPublicKey.xml` over:

```php
$gateway->setPublicKeyXml(file_get_contents('/path/to/PaymentPublicKey.xml'));
```

### Logger (PSR-3, optional)

The package never writes to files or the error log on its own. Inject any PSR-3 logger and the gateway forwards it to
every request, which logs the full SOAP traffic on `debug` level, operation results on `info`/`warning` and transport
problems on `error` level:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$gateway->setLogger(new Logger('pluxee', [new StreamHandler('php://stderr')]));
```

With Laravel:

```php
$gateway->setLogger(\Illuminate\Support\Facades\Log::channel('pluxee'));
```

Without a logger a `Psr\Log\NullLogger` is used and nothing is logged.

### Checkout (OTP Payment)

```php
$response = $gateway->purchase([
    'amount'        => '25.00',       // order total (TRY)
    'transactionId' => 'ORDER-12345', // becomes ExternalRrn (or pass externalRrn explicitly)
    'gsm'           => '5551234567',  // 10 digits, 5XXXXXXXXX (05xx / +90 5xx also accepted)
    'otp'           => '413256',      // single-use code from the Pluxee mobile app
    'externalInfo'  => 'web',         // optional breakdown info
])->send();

if ($response->isSuccessful()) {
    echo 'Paid. Pluxee RRN: ' . $response->getTransactionReference();
} else {
    echo 'Payment failed: ' . $response->getMessage();
    echo 'Result code: ' . $response->getCode();
}
```

Split payments (part of the cart with Pluxee, the rest with a credit card) are orchestrated by the platform: charge
the card first, then run the Pluxee payment and commit or cancel the card authorisation based on its result.

### Refund (full or partial)

```php
$response = $gateway->refund([
    'amount'               => '10.00',
    'originalRrn'          => '000010401127', // Pluxee RRN of the sale (or transactionReference)
    'externalRrn'          => 'REFUND-1',     // optional
])->send();

if ($response->isSuccessful()) {
    echo 'Refunded. Refund RRN: ' . $response->getTransactionReference();
}
```

### Void (cancel a sale before day-end)

```php
$response = $gateway->void([
    'amount'        => '25.00',
    'transactionId' => 'ORDER-VOID-1',
    'rrn'           => '000010401127', // Pluxee RRN of the sale
])->send();
```

### Technical Reversals (timeout recovery)

When `MakePayment` or `void` produces no answer (timeout, connection dropped), use the technical reversal with the
same `externalRrn` (sale) or `rrn` (void) as the original call: if Pluxee processed the original operation it is
cancelled, otherwise nothing happens.

```php
// The original sale was sent with externalRrn "ORDER-12345" but timed out.
$gateway->reverse([
    'amount'      => '25.00',
    'externalRrn' => 'ORDER-12345',
])->send();

// The original void was sent with rrn "000010401127" but timed out.
$gateway->reverseOfVoid([
    'amount' => '25.00',
    'rrn'    => '000010401127',
])->send();
```

### Merchant Virtual POS Setup

Fetch (or re-fetch) the `merchantNo` / `terminalNo` Pluxee assigned to a merchant, e.g. right after the merchant
signs up with its VKN:

```php
$response = $gateway->setupMerchantVirtualPos(['vkn' => '1111111111'])->send();

if ($response->isSuccessful()) {
    $merchantNo = $response->getMerchantNo();
    $terminalNo = $response->getTerminalNo();
}
```

### Credentials Check

```php
$response = $gateway->login()->send();

if ($response->isSuccessful()) {
    // credentials are valid; $response->getToken() is the WS token
}
```

## Token Handling

Every operation runs with two tokens: the WS token (`LoginWsUser`) and a single-use payment token
(`CreateActionToken`). The package fetches them lazily and refreshes them transparently:

- result **5 / 6** – the WS token expired: log in again and retry the operation once,
- result **33 / 34** – the payment token expired: create a new one and retry the operation once.

Token refreshes are logged as `warning`; the caller never sees them as failed payments.

## Response Objects

All responses share `isSuccessful()`, `getCode()` (`ResultCode`), `getMessage()` (`ResultMessage`) and `getData()`.
`PurchaseResponse` additionally offers:

| Method | Description |
|--------|-------------|
| `getTransactionReference()` | Pluxee RRN – keep it for refunds/voids |
| `getBatchNo()` | Pluxee day-end (günsonu) batch number |
| `getAvailablePoint()` / `getHostBalance()` / `getOtc()` | Balance/point info when the contract returns it |
| `getServiceId()` / `getTransactionEncId()` | Raw service identifiers |
| `getSignature()` | Raw `RequestSignature` from Pluxee |
| `isSignatureValid()` | `true`/`false` when `hmacKey` is configured, otherwise `null` |

`RefundResponse` offers `getTransactionReference()` and `getBatchNo()`;
`SetupMerchantVirtualPosResponse` offers `getMerchantNo()` and `getTerminalNo()`.

## Supported Methods

| Method | PluxeePay operation | Description |
|--------|--------------------|-------------|
| `purchase()` | `MakePayment` (TxnStatus `N`) | OTP payment |
| `void()` | `MakePayment` (TxnStatus `V`) | Sale cancellation before day-end |
| `reverse()` | `MakePayment` (TxnStatus `R`) | Technical reversal of a sale |
| `reverseOfVoid()` | `MakePayment` (TxnStatus `RV`) | Technical reversal of a void |
| `refund()` | `MakeRefund` (TxnStatus `N`) | Full or partial refund |
| `setupMerchantVirtualPos()` | `SetUpMerchantVirtualPos` | Fetch merchantNo / terminalNo by VKN |
| `login()` | `LoginWsUser` | Validate WS credentials |
| `createActionToken()` | `CreateActionToken` | Fetch a single-use payment token |

## Testing

```bash
composer test        # run the test suite
composer analyse     # phpstan
composer lint-check  # pint --test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
