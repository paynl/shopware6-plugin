<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Enums;

use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class PaynlTransactionStatusesEnum
{
    const STATUS_CANCEL = -90;
    const STATUS_EXPIRED = -80;
    const STATUS_REFUNDING = -72;
    const STATUS_REFUND = -81;
    const STATUS_PENDING_20 = 20;
    const STATUS_PENDING_25 = 25;
    const STATUS_PENDING_50 = 50;
    const STATUS_PENDING_90 = 90;
    const STATUS_VERIFY = 85;
    const STATUS_AUTHORIZE = 95;
    const STATUS_PARTLY_CAPTURED = 97;
    const STATUS_PAID = 100;
    const STATUS_PAID_CHECKAMOUNT = -51;
    const STATUS_FAILURE = -60;
    const STATUS_DENIED_63 = -63;
    const STATUS_DENIED_64 = -64;
    const STATUS_CHARGEBACK = -71;
    const STATUS_PARTIAL_REFUND = -82;
    const STATUS_PARTIAL_PAYMENT = 80;

    const DENIED_STATUSES = [
        self::STATUS_DENIED_63,
        self::STATUS_DENIED_64,
    ];

    const STATUSES_ARRAY = [
        self::STATUS_CANCEL => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_EXPIRED => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_PAID_CHECKAMOUNT => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_FAILURE => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_DENIED_63 => StateMachineTransitionActions::ACTION_CANCEL,
        self::STATUS_DENIED_64 => StateMachineTransitionActions::ACTION_CANCEL,

        self::STATUS_REFUNDING => StateMachineStateEnum::ACTION_REFUNDING,
        self::STATUS_REFUND => StateMachineTransitionActions::ACTION_REFUND,

        self::STATUS_PENDING_20 => StateMachineTransitionActions::ACTION_DO_PAY,
        self::STATUS_PENDING_25 => StateMachineTransitionActions::ACTION_DO_PAY,
        self::STATUS_PENDING_50 => StateMachineTransitionActions::ACTION_DO_PAY,
        self::STATUS_PENDING_90 => StateMachineTransitionActions::ACTION_DO_PAY,

        self::STATUS_VERIFY => StateMachineStateEnum::ACTION_VERIFY,
        self::STATUS_AUTHORIZE => StateMachineStateEnum::ACTION_AUTHORIZE,
        self::STATUS_PARTLY_CAPTURED => StateMachineStateEnum::ACTION_PARTLY_CAPTURED,
        self::STATUS_PAID => StateMachineTransitionActions::ACTION_PAID,
        self::STATUS_PARTIAL_REFUND => StateMachineTransitionActions::ACTION_REFUND_PARTIALLY,
        self::STATUS_PARTIAL_PAYMENT => StateMachineTransitionActions::ACTION_PAID_PARTIALLY,
    ];
}
