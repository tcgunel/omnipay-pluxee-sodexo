<?php

namespace Omnipay\PluxeeSodexo\Constants;

/**
 * SOAP namespaces of the PluxeePay services.
 *
 * The contract namespaces still use the legacy "SodexoPayment" naming; the
 * document (v5.3) and the live services expect them exactly as-is.
 */
class Soap
{
    public const LOGIN_SERVICE_NAMESPACE = 'http://www.sodexopayment.com/LoginService/2019/04';

    /**
     * Service contract names. WCF routes on the SOAPAction header
     * ("<namespace>/<contract>/<operation>") and answers anything else with
     * a generic "Sistemsel bir hata" fault.
     */
    public const LOGIN_CONTRACT = 'ILoginWS';

    public const PAYMENT_CONTRACT = 'IPaymentWS';

    public const LOGIN_MODEL_NAMESPACE = 'http://schemas.datacontract.org/2004/07/SodexoPayment.Common.Model.Login';

    public const PAYMENT_SERVICE_NAMESPACE = 'http://www.sodexopayment.com/PaymentService/2019/04';

    public const PAYMENT_MODEL_NAMESPACE = 'http://schemas.datacontract.org/2004/07/SodexoPayment.Common.Model.Payment';

    public static function action(string $serviceNamespace, string $contract, string $operation): string
    {
        return $serviceNamespace . '/' . $contract . '/' . $operation;
    }
}
