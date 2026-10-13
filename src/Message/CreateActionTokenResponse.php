<?php

namespace Omnipay\PluxeeSodexo\Message;

/**
 * CreateActionToken response.
 */
class CreateActionTokenResponse extends RemoteAbstractResponse
{
    public function getToken(): ?string
    {
        return $this->value('Token');
    }
}
