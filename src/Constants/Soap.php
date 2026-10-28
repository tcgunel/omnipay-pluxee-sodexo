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

    /**
     * Element order of each request contract, from the UAT XSDs. WCF's
     * DataContractSerializer reads the members in exactly this order and
     * rejects an element it isn't expecting at that point ("Expecting element
     * 'Password'"), so the fields are sent in this order whatever order the
     * request builds them in.
     *
     * @var array<string, array<int, string>>
     */
    public const FIELD_ORDER = [
        'LoginWsUser' => ['Password', 'UserName'],
        'CreateActionToken' => ['ActionType', 'AffiliateType', 'CardHash', 'ExternalProfileId', 'WsToken'],
        'SetUpMerchantVirtualPos' => ['VKN', 'WsToken'],
        'MakePayment' => [
            'WsToken', 'TxnType', 'TxnStatus', 'MerchantNo', 'TerminalNo', 'ExternalRrn', 'Rrn',
            'TxnCode', 'ServiceId', 'Amount', 'Gsm', 'CategoryId', 'ExternalInfo', 'PaymentMethod', 'AuthCode',
        ],
        'MakeRefund' => [
            'WsToken', 'TxnStatus', 'MerchantNo', 'TerminalNo', 'TxnCode', 'Amount', 'OriginalRrn',
            'ExternalRrn', 'Rrn', 'ExternalInfo', 'TransactionEncId', 'PaymentMethod',
        ],
    ];

    public static function action(string $serviceNamespace, string $contract, string $operation): string
    {
        return $serviceNamespace . '/' . $contract . '/' . $operation;
    }
}
