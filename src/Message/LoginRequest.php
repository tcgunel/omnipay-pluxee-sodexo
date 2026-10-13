<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * LoginWsUser - explicit login, mainly useful to validate the WS credentials
 * stored for a shop (merchant panel "test connection").
 */
class LoginRequest extends RemoteAbstractRequest
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
            'UserName' => (string) $this->getUserName(),
            'Password' => (string) $this->getPassword(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendData($data): LoginResponse
    {
        $result = $this->callLoginService('LoginWsUser', 'wsUserInfo', $data, false);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): LoginResponse
    {
        return $this->response = new LoginResponse($this, $data);
    }
}
