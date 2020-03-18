<?php

namespace PaynlPayment\Enums;

class StateMachineStateEnum
{
    public const ACTION_VERIFY = 'verify';
    public const ACTION_AUTHORIZE = 'authorize';
    public const ACTION_PARTLY_CAPTURED = 'partly_captured';
}
