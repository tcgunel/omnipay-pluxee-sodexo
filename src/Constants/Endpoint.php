<?php

namespace Omnipay\PluxeeSodexo\Constants;

/**
 * PluxeePay service endpoints (PluxeePay Integration v5.3).
 *
 * All services are IP restricted: Pluxee must whitelist the merchant
 * platform's static IP before any call succeeds.
 */
class Endpoint
{
    public const LOGIN_UAT = 'https://payment.partners.uat.pluxee.com.tr/LoginWS.svc';

    public const LOGIN_LIVE = 'https://payment.partners.pluxee.com.tr/LoginWS.svc';

    public const PAYMENT_UAT = 'https://payment.partners.uat.pluxee.com.tr/PaymentWS.svc';

    public const PAYMENT_LIVE = 'https://payment.partners.pluxee.com.tr/PaymentWS.svc';

    public static function login(bool $testMode): string
    {
        return $testMode ? self::LOGIN_UAT : self::LOGIN_LIVE;
    }

    public static function payment(bool $testMode): string
    {
        return $testMode ? self::PAYMENT_UAT : self::PAYMENT_LIVE;
    }
}
