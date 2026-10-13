<?php

namespace Omnipay\PluxeeSodexo\Constants;

/**
 * MakePayment TxnStatus values.
 */
class TxnStatus
{
    public const SALE = 'N';

    public const VOID = 'V';

    public const REVERSE = 'R';

    public const REVERSE_OF_VOID = 'RV';
}
