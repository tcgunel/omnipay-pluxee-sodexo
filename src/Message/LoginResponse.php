<?php

namespace Omnipay\PluxeeSodexo\Message;

/**
 * LoginWsUser response.
 */
class LoginResponse extends RemoteAbstractResponse
{
    public function getToken(): ?string
    {
        return $this->value('Token');
    }
}
