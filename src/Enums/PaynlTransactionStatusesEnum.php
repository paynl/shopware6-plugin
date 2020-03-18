<?php

namespace PaynlPayment\Enums;

class PaynlTransactionStatusesEnum
{
    public const STATUS_CANCEL = -90;
    public const STATUS_EXPIRED = -80;
    public const STATUS_REFUNDING = -72;
    public const STATUS_REFUND = -81;
    public const STATUS_PENDING_20 = 20;
    public const STATUS_PENDING_25 = 25;
    public const STATUS_PENDING_50 = 50;
    public const STATUS_PENDING_90 = 90;
    public const STATUS_VERIFY = 85;
    public const STATUS_AUTHORIZE = 95;
    public const STATUS_PARTLY_CAPTURED = 97;
    public const STATUS_PAID = 100;
    public const STATUS_PAID_CHECKAMOUNT = -51;
    public const STATUS_FAILURE = -60;
    public const STATUS_DENIED_63 = -63;
    public const STATUS_DENIED_64 = -64;
    public const STATUS_CHARGEBACK = -71;
    public const STATUS_PARTIAL_REFUND = -82;
    public const STATUS_PARTIAL_PAYMENT = 80;
}
