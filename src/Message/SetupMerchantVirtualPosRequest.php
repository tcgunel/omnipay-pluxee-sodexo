<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Exception\InvalidResponseException;

/**
 * SetUpMerchantVirtualPos - fetch the merchantNo / terminalNo Pluxee assigned
 * to a merchant (VKN) and store them on the shop for payment operations.
 *
 * Runs against LoginWS and only needs the WS credentials.
 */
class SetupMerchantVirtualPosRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, string>
     */
    public function getData(): array
    {
        $this->validateCredentials();
        $this->validate('vkn');

        return [
            'VKN' => (string) $this->getVkn(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendData($data): SetupMerchantVirtualPosResponse
    {
        $result = $this->callLoginService('SetUpMerchantVirtualPos', 'setUpMerchantVirtualPosRequest', $data, true);

        if ($result === []) {
            throw new InvalidResponseException('Pluxee SetUpMerchantVirtualPos returned an empty response.');
        }

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): SetupMerchantVirtualPosResponse
    {
        return $this->response = new SetupMerchantVirtualPosResponse($this, $data);
    }
}
