<?php

namespace Omnipay\PluxeeSodexo\Constants;

/**
 * Result codes returned by the PluxeePay services.
 *
 * 0 is success. The token related codes deserve special handling:
 *  - 5 / 6: the WS token (LoginWsUser) expired, log in again,
 *  - 33 / 34: the single-use payment token expired, call CreateActionToken
 *    again and retry the operation with the new token.
 */
class ResultCode
{
    public const SUCCESS = '0';

    public const LOGIN_TOKEN_EXPIRED = ['5', '6'];

    public const ACTION_TOKEN_EXPIRED = ['33', '34'];

    public static function isLoginTokenExpired(int|string|null $code): bool
    {
        return in_array((string) $code, self::LOGIN_TOKEN_EXPIRED, true);
    }

    public static function isActionTokenExpired(int|string|null $code): bool
    {
        return in_array((string) $code, self::ACTION_TOKEN_EXPIRED, true);
    }
}
