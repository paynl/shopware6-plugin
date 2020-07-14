<?php

namespace PaynlPayment\Shopware6\Enums;

class StateMachineStateEnum
{
    public const ACTION_VERIFY = 'paynl_verify';
    public const ACTION_AUTHORIZE = 'paynl_authorize';
    public const ACTION_PARTLY_CAPTURED = 'paynl_partly_captured';
}
