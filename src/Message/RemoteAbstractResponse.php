<?php

namespace Omnipay\PluxeeSodexo\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\PluxeeSodexo\Constants\ResultCode;

/**
 * Shared behaviour for every PluxeePay response: the services answer with a
 * ResultCode (0 = success) and a ResultMessage.
 */
abstract class RemoteAbstractResponse extends AbstractResponse
{
    /**
     * @var array<string, mixed>
     */
    protected array $result = [];

    /**
     * @param  mixed  $data
     */
    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);

        $this->result = is_array($data) ? $data : [];
    }

    public function isSuccessful(): bool
    {
        return $this->getCode() === ResultCode::SUCCESS;
    }

    public function getCode(): ?string
    {
        return $this->value('ResultCode');
    }

    public function getMessage(): ?string
    {
        return $this->value('ResultMessage');
    }

    public function getResultCode(): ?string
    {
        return $this->getCode();
    }

    public function getResultMessage(): ?string
    {
        return $this->getMessage();
    }

    /**
     * True when the failure was caused by an expired WS or payment token,
     * i.e. the request can simply be retried.
     */
    public function isTokenExpired(): bool
    {
        $code = $this->getCode();

        return ResultCode::isLoginTokenExpired($code) || ResultCode::isActionTokenExpired($code);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData()
    {
        return $this->result;
    }

    protected function value(string $key): ?string
    {
        if (! array_key_exists($key, $this->result) || $this->result[$key] === null) {
            return null;
        }

        return (string) $this->result[$key];
    }
}
