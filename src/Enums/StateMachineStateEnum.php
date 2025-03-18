<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Enums;

class StateMachineStateEnum
{
    public const ACTION_VERIFY = 'verify';
    public const ACTION_AUTHORIZE = 'authorize';
    public const ACTION_PARTLY_CAPTURED = 'partly_captured';
    public const ACTION_REFUNDING = 'refunding';
    public const STATE_COMPLETED = 'completed';
}
