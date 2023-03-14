<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Paynl;

use Exception;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class TransactionStateService
{
    /** @var ProcessingHelper */
    private $processingHelper;

    /**
     * @param ProcessingHelper $processingHelper
     */
    public function __construct(ProcessingHelper $processingHelper)
    {
        $this->processingHelper = $processingHelper;
    }

    /**
     * @param string $transactionId
     * @throws Exception
     */
    public function cancel(string $transactionId): void
    {
        $this->processingHelper->updatePaymentStateByTransactionId(
            $transactionId,
            StateMachineTransitionActions::ACTION_CANCEL,
            PaynlTransactionStatusesEnum::STATUS_CANCEL
        );
    }

    /**
     * @param string $transactionId
     * @throws Exception
     */
    public function fail(string $transactionId): void
    {
        $this->processingHelper->updatePaymentStateByTransactionId(
            $transactionId,
            StateMachineTransitionActions::ACTION_FAIL,
            PaynlTransactionStatusesEnum::STATUS_FAILURE
        );
    }
}
