<?php


namespace PaynlPayment\Enums;


class PaynlTransactionStatusesEnum
{
    public const STATUS_PENDING = 17;
    public const STATUS_CANCEL = 35;
    public const STATUS_PAID = 12;
    public const STATUS_NEEDS_REVIEW = 21;
    public const STATUS_REFUND = 20;
    public const STATUS_PARTIAL_REFUND = -82;
    public const STATUS_AUTHORIZED = 18;
}
