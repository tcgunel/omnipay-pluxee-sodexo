<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\PluxeeSodexo\Constants\ActionType;

/**
 * CreateActionToken - explicit single-use payment token creation. Requests
 * already obtain their own token, so this is only needed for diagnostics or
 * custom flows.
 */
class CreateActionTokenRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, string>
     *
     * @throws InvalidRequestException
     */
    public function getData(): array
    {
        $this->validateCredentials();

        return [
            'ActionType' => ActionType::SODEXO_PAYMENT,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendData($data): CreateActionTokenResponse
    {
        $result = $this->callLoginService('CreateActionToken', 'actionTokenRequest', $data, true);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): CreateActionTokenResponse
    {
        return $this->response = new CreateActionTokenResponse($this, $data);
    }
}
