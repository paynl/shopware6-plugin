<?php

namespace PaynlPayment\Shopware6\Enums;

use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

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

    public const STATUSES_ARRAY = [
        self::STATUS_CANCEL => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_EXPIRED => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_PAID_CHECKAMOUNT => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_FAILURE => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_DENIED_63 => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_DENIED_64 => StateMachineTransitionActions::ACTION_CANCEL,

        self::STATUS_REFUNDING => StateMachineTransitionActions::ACTION_REFUND,
        self::STATUS_REFUND => StateMachineTransitionActions::ACTION_REFUND,

        self::STATUS_PENDING_20 => StateMachineTransitionActions::ACTION_REOPEN,
        self::STATUS_PENDING_25 => StateMachineTransitionActions::ACTION_REOPEN,
        self::STATUS_PENDING_50 => StateMachineTransitionActions::ACTION_REOPEN,
        self::STATUS_PENDING_90 => StateMachineTransitionActions::ACTION_REOPEN,

        self::STATUS_VERIFY => StateMachineStateEnum::ACTION_VERIFY,
        self::STATUS_AUTHORIZE => StateMachineStateEnum::ACTION_AUTHORIZE,
        self::STATUS_PARTLY_CAPTURED => StateMachineStateEnum::ACTION_PARTLY_CAPTURED,
        self::STATUS_PAID => StateMachineTransitionActions::ACTION_DO_PAY,
        self::STATUS_PARTIAL_REFUND => StateMachineTransitionActions::ACTION_REFUND_PARTIALLY,
        self::STATUS_PARTIAL_PAYMENT => StateMachineTransitionActions::ACTION_PAID_PARTIALLY,
    ];
}
