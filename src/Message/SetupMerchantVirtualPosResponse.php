<?php

namespace Omnipay\PluxeeSodexo\Message;

/**
 * SetUpMerchantVirtualPos response - the merchantNo / terminalNo Pluxee
 * assigned to the merchant, to be stored per shop.
 */
class SetupMerchantVirtualPosResponse extends RemoteAbstractResponse
{
    public function getMerchantNo(): ?string
    {
        return $this->value('MerchantNo');
    }

    public function getTerminalNo(): ?string
    {
        return $this->value('TerminalNo');
    }
}
