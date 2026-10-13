<?php

namespace Omnipay\PluxeeSodexo\Traits;

/**
 * Shared getters/setters for the gateway and every request.
 *
 * The gateway holds these parameters in its parameter bag; createRequest()
 * copies them into each request, so both classes use the same trait.
 */
trait HasParameters
{
    public function getUserName()
    {
        return $this->getParameter('userName');
    }

    public function setUserName($value)
    {
        return $this->setParameter('userName', $value);
    }

    public function getPassword()
    {
        return $this->getParameter('password');
    }

    public function setPassword($value)
    {
        return $this->setParameter('password', $value);
    }

    public function getMerchantNo()
    {
        return $this->getParameter('merchantNo');
    }

    public function setMerchantNo($value)
    {
        return $this->setParameter('merchantNo', $value);
    }

    public function getTerminalNo()
    {
        return $this->getParameter('terminalNo');
    }

    public function setTerminalNo($value)
    {
        return $this->setParameter('terminalNo', $value);
    }

    public function getVkn()
    {
        return $this->getParameter('vkn');
    }

    public function setVkn($value)
    {
        return $this->setParameter('vkn', $value);
    }

    public function getPublicKeyModulus()
    {
        return $this->getParameter('publicKeyModulus');
    }

    public function setPublicKeyModulus($value)
    {
        return $this->setParameter('publicKeyModulus', $value);
    }

    public function getPublicKeyExponent()
    {
        return $this->getParameter('publicKeyExponent');
    }

    public function setPublicKeyExponent($value)
    {
        return $this->setParameter('publicKeyExponent', $value);
    }

    public function getPublicKeyXml()
    {
        return $this->getParameter('publicKeyXml');
    }

    public function setPublicKeyXml($value)
    {
        return $this->setParameter('publicKeyXml', $value);
    }

    public function getHmacKey()
    {
        return $this->getParameter('hmacKey');
    }

    public function setHmacKey($value)
    {
        return $this->setParameter('hmacKey', $value);
    }

    public function getLoginWsUrl()
    {
        return $this->getParameter('loginWsUrl');
    }

    public function setLoginWsUrl($value)
    {
        return $this->setParameter('loginWsUrl', $value);
    }

    public function getPaymentWsUrl()
    {
        return $this->getParameter('paymentWsUrl');
    }

    public function setPaymentWsUrl($value)
    {
        return $this->setParameter('paymentWsUrl', $value);
    }

    public function getOtp()
    {
        return $this->getParameter('otp');
    }

    public function setOtp($value)
    {
        return $this->setParameter('otp', $value);
    }

    public function getGsm()
    {
        return $this->getParameter('gsm');
    }

    public function setGsm($value)
    {
        return $this->setParameter('gsm', $value);
    }

    public function getExternalRrn()
    {
        return $this->getParameter('externalRrn');
    }

    public function setExternalRrn($value)
    {
        return $this->setParameter('externalRrn', $value);
    }

    public function getExternalInfo()
    {
        return $this->getParameter('externalInfo');
    }

    public function setExternalInfo($value)
    {
        return $this->setParameter('externalInfo', $value);
    }

    public function getRrn()
    {
        return $this->getParameter('rrn');
    }

    public function setRrn($value)
    {
        return $this->setParameter('rrn', $value);
    }

    public function getOriginalRrn()
    {
        return $this->getParameter('originalRrn');
    }

    public function setOriginalRrn($value)
    {
        return $this->setParameter('originalRrn', $value);
    }
}
