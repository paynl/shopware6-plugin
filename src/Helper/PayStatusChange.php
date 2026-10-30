<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

final class PayStatusChange
{
    public const APPROVE = 'approve';
    public const DECLINE = 'decline';
    public const CAPTURE = 'capture';
    public const VOID = 'void';

    public function __construct(
        public readonly ?string $remoteCall,
        public readonly int $localStateId
    ) {
    }

    public static function localStateId(string $action): ?int
    {
        if ($action === StateMachineTransitionActions::ACTION_PAID) {
            return PaynlTransactionStatusesEnum::STATUS_PAID;
        }
        if ($action === StateMachineTransitionActions::ACTION_CANCEL) {
            return PaynlTransactionStatusesEnum::STATUS_CANCEL;
        }

        return null;
    }

    public static function decide(
        string $action,
        bool $beingVerified,
        bool $authorized,
        int $remoteStatusCode
    ): ?self {
        $localStateId = self::localStateId($action);
        if ($localStateId === null) {
            return null;
        }

        if ($beingVerified) {
            $remoteCall = $action === StateMachineTransitionActions::ACTION_PAID ? self::APPROVE : self::DECLINE;

            return new self($remoteCall, $localStateId);
        }
        if ($authorized) {
            $remoteCall = $action === StateMachineTransitionActions::ACTION_PAID ? self::CAPTURE : self::VOID;

            return new self($remoteCall, $localStateId);
        }
        if ($remoteStatusCode === $localStateId) {
            return new self(null, $localStateId);
        }

        return null;
    }
}
