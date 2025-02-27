<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Exception;
use PayNL\Sdk\Exception\PayException;
use PayNL\Sdk\Model\Pay\PayOrder;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use PaynlPayment\Shopware6\Repository\StateMachineTransition\StateMachineTransitionRepositoryInterface;
use PaynlPayment\Shopware6\Service\Order\OrderStatusUpdater;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use Throwable;

class ProcessingHelper
{
    private Api $paynlApi;
    private LoggerInterface $logger;
    private PaynlTransactionsRepositoryInterface $paynlTransactionRepository;
    private OrderTransactionRepositoryInterface $orderTransactionRepository;
    private StateMachineTransitionRepositoryInterface $stateMachineTransitionRepository;
    private StateMachineRegistry $stateMachineRegistry;
    private OrderStatusUpdater $orderStatusUpdater;

    public function __construct(
        Api $api,
        LoggerInterface $logger,
        PaynlTransactionsRepositoryInterface $paynlTransactionRepository,
        OrderTransactionRepositoryInterface $orderTransactionRepository,
        StateMachineTransitionRepositoryInterface $stateMachineTransitionRepository,
        StateMachineRegistry $stateMachineRegistry,
        OrderStatusUpdater $orderStatusUpdater
    ) {
        $this->paynlApi = $api;
        $this->logger = $logger;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineTransitionRepository = $stateMachineTransitionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->orderStatusUpdater = $orderStatusUpdater;
    }

    public function storePaynlTransactionData(
        OrderEntity $order,
        OrderTransactionEntity $orderTransaction,
        SalesChannelContext $salesChannelContext,
        string $paynlTransactionId,
        ?Throwable $exception = null
    ): void {
        $paymentId = $this->paynlApi->getPaynlPaymentMethodIdFromShopware($salesChannelContext);
        /** @var CustomerEntity $customer */
        $customer = $salesChannelContext->getCustomer();
        $transactionData = [
            'paynlTransactionId' => $paynlTransactionId,
            'customerId' => $customer->getId(),
            'orderId' => $order->getId(),
            'orderTransactionId' => $orderTransaction->getId(),
            'paymentId' => $paymentId,
            'amount' => $order->getAmountTotal(),
            'latestActionName' => StateMachineTransitionActions::ACTION_REOPEN,
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
            'orderStateId' => $order->getStateId(),
            // TODO: check sComment from shopware5 plugin
            'dispatch' => $salesChannelContext->getShippingMethod()->getId(),
            'exception' => (string)$exception,
        ];
        $this->paynlTransactionRepository->create([$transactionData], $salesChannelContext->getContext());
    }

    /**
     * @throws PayException
     * @throws Exception
     */
    public function notifyActionUpdateTransactionByPayTransactionId(string $paynlTransactionId): string
    {
        $paynlTransactionEntity = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);

        $payTransactionStatus = $this->paynlApi->getTransactionStatus(
            $paynlTransactionId,
            $paynlTransactionEntity->getOrder()->getSalesChannelId()
        );

        if ($this->checkDoubleOrderTransactions($paynlTransactionEntity, Context::createDefaultContext())) {
            $this->updateOldPayTransactionStatus($paynlTransactionEntity, $payTransactionStatus);

            return "TRUE| The current transaction's commands are ignored due to a later completed transaction.";
        }

        $transitionName = $this->getOrderActionNameByPaynlTransactionStatusCode($payTransactionStatus->getStatusCode());

        $this->updateTransactionStatus($paynlTransactionEntity, $transitionName, $payTransactionStatus->getStatusCode());

        if ($this->isUnprocessedTransactionState($payTransactionStatus)) {
            return sprintf('TRUE| No change made (%s)', $payTransactionStatus->getStatusName());
        }

        $this->logger->info('PAY. transaction was successfully updated', [
            'transactionId' => $paynlTransactionId,
            'statusCode' => $payTransactionStatus->getStatusCode()
        ]);

        return sprintf(
            'TRUE| Status updated to: %s (%s) orderNumber: %s',
            $payTransactionStatus->getStatusName(),
            $payTransactionStatus->getStatusCode(),
            $payTransactionStatus->getOrderId()
        );
    }

    private function updateOldPayTransactionStatus(
        PaynlTransactionEntity $paynlTransaction,
        PayOrder $payTransactionStatus
    ): void {
        $transitionName = $this->getOrderActionNameByPaynlTransactionStatusCode($payTransactionStatus->getStatusCode());

        $criteria = new Criteria();
        $stateMachineId = $paynlTransaction->getOrderTransaction()->getStateMachineState()->getStateMachineId();
        $fromStateId = $paynlTransaction->getOrderTransaction()->getStateMachineState()->getId();
        $transitions = $this->stateMachineTransitionRepository->search(
            $criteria->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('actionName', $transitionName),
                        new EqualsFilter('stateMachineId', $stateMachineId),
                        new EqualsFilter('fromStateId', $fromStateId),
                    ]
                )),
            Context::createDefaultContext()
        );

        $stateId = $transitions->first() ? $transitions->first()->get('toStateId') : '';
        if (empty($stateId)) {
            return;
        }

        $this->updatePaynlTransactionStatus(
            $paynlTransaction->getId(),
            $payTransactionStatus->getStatusCode(),
            $transitionName,
            $stateId
        );
    }

    /**
     * @param string $orderId
     * @return void
     * @throws Exception
     */
    public function returnUrlActionUpdateTransactionByOrderId(string $orderId): void
    {
        $paynlTransactionEntity = $this->getPaynlTransactionEntityByOrderId($orderId);

        $paynlTransactionId = $paynlTransactionEntity->getPaynlTransactionId();
        $salesChannelId = $paynlTransactionEntity->getOrder()->getSalesChannelId();

        $payTransactionStatus = $this->paynlApi->getTransactionStatus($paynlTransactionId, $salesChannelId);
        $transitionName = $this->getOrderActionNameByPaynlTransactionStatusCode($payTransactionStatus->getStatusCode());

        $this->updateTransactionStatus($paynlTransactionEntity, $transitionName, $payTransactionStatus->getStatusCode());

        $this->logger->info('Transaction status was successfully updated', [
            'transactionId' => $paynlTransactionId,
            'statusCode' => $payTransactionStatus->getStatusCode()
        ]);
    }

    /**
     * @throws PayException
     * @throws Exception
     */
    public function refundActionUpdateTransactionByTransactionId(string $paynlTransactionId): void
    {
        /** @var PaynlTransactionEntity $transactionEntity */
        $paynlTransactionEntity = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);
        $salesChannelId = $paynlTransactionEntity->getOrder()->getSalesChannelId();

        $payTransactionStatus = $this->paynlApi->getTransactionStatus($paynlTransactionId, $salesChannelId);
        $transitionName = $this->getOrderActionNameByPaynlTransactionStatusCode($payTransactionStatus->getStatusCode());

        $this->updateTransactionStatus($paynlTransactionEntity, $transitionName, $payTransactionStatus->getStatusCode());
    }

    /**
     * @param string $paynlTransactionId
     * @param string $transitionName
     * @param int $paynlTransactionStatusCode
     * @return void
     */
    public function instorePaymentUpdateState(
        string $paynlTransactionId,
        string $transitionName,
        int $paynlTransactionStatusCode
    ): void {
        /** @var PaynlTransactionEntity $transactionEntity */
        $paynlTransactionEntity = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);

        $this->updateTransactionStatus($paynlTransactionEntity, $transitionName, $paynlTransactionStatusCode);
    }

    public function processNotify(string $paynlTransactionId): string
    {
        try {
            return $this->notifyActionUpdateTransactionByPayTransactionId($paynlTransactionId);
        } catch (Throwable $e) {
            $this->logger->error('Error on notifying transaction.', [
                'transactionId' => $paynlTransactionId,
                'exception' => $e
            ]);

            return sprintf(
                'FALSE| Error "%s" in file %s',
                $e->getMessage(),
                $e->getFile()
            );
        }
    }

    /**
     * @throws PaynlTransactionException
     * @throws PayException
     * @throws Exception
     */
    public function processChangePayNLStatus(
        string $paynlId,
        string $paynlTransactionId,
        string $currentActionName,
        string $salesChannelId
    ): void {
        $payTransactionStatus = $this->paynlApi->getTransactionStatus($paynlTransactionId, $salesChannelId);
        $paynlTransactionEntity = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);

        if ($payTransactionStatus->isBeingVerified()
            && $currentActionName === StateMachineTransitionActions::ACTION_PAID
        ) {
            $this->updatePaynlTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_PAID,
                $currentActionName
            );

            $this->paynlApi->approve($paynlTransactionId, $salesChannelId);

        } elseif ($payTransactionStatus->isBeingVerified()
            && $currentActionName === StateMachineTransitionActions::ACTION_CANCEL
        ) {
            $this->updatePaynlTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_CANCEL,
                $currentActionName
            );

            $this->paynlApi->decline($paynlTransactionId, $salesChannelId);

        } elseif ($payTransactionStatus->isAuthorized()
            && $currentActionName === StateMachineTransitionActions::ACTION_PAID
        ) {
            $this->updatePaynlTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_PAID,
                $currentActionName
            );

            $this->paynlApi->capture($paynlTransactionId, null, $salesChannelId);

        } elseif ($payTransactionStatus->isAuthorized()
            && $currentActionName === StateMachineTransitionActions::ACTION_CANCEL
        ) {
            $this->updatePaynlTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_CANCEL,
                $currentActionName
            );

            $this->paynlApi->void($paynlTransactionId, $salesChannelId);
        } else {
            return;
        }

        $this->orderStatusUpdater->updateOrderStatus(
            $paynlTransactionEntity->getOrder(),
            $payTransactionStatus->getStatusCode(),
            $salesChannelId,
            Context::createDefaultContext()
        );
    }

    /**
     * @param PaynlTransactionEntity $paynlTransactionEntity
     * @param string $transitionName
     * @param int $paynlTransactionStatusCode
     * @return void
     * @throws Exception
     */
    private function updateTransactionStatus(
        PaynlTransactionEntity $paynlTransactionEntity,
        string $transitionName,
        int $paynlTransactionStatusCode
    ): void {
        $orderTransaction = $paynlTransactionEntity->getOrderTransaction();
        $orderTransactionId = $paynlTransactionEntity->get('orderTransactionId') ?: '';
        $stateMachineStateId = $orderTransaction->getStateId();
        $stateMachineId = $orderTransaction->getStateMachineState()->getStateMachineId();
        $context = Context::createDefaultContext();

        $allowedTransitions = $this->getAllowedTransitions($transitionName, $stateMachineId, $stateMachineStateId);

        if ($this->isPaidPartlyToPaidTransition($transitionName, $orderTransactionId, $context)) {
            $allowedTransitions = $this->getAllowedTransitions(
                StateMachineTransitionActions::ACTION_DO_PAY,
                $stateMachineId,
                $stateMachineStateId
            );
        }

        if (
            !empty($transitionName)
            && ($transitionName !== $paynlTransactionEntity->getLatestActionName())
            && ($allowedTransitions > 0)
        ) {
            $stateMachine = $this->manageOrderTransactionStateTransition(
                $orderTransactionId,
                $transitionName
            );

            /** @var StateMachineStateEntity $stateMachineStateEntity */
            $stateMachineStateEntity = $stateMachine->get('toPlace');
            $stateMachineStateId = $stateMachineStateEntity->getUniqueIdentifier();
        }

        $this->updatePaynlTransactionStatus(
            $paynlTransactionEntity->getId(),
            $paynlTransactionStatusCode,
            $transitionName,
            $stateMachineStateId
        );

        $this->orderStatusUpdater->updateOrderStatus(
            $paynlTransactionEntity->getOrder(),
            $paynlTransactionStatusCode,
            $paynlTransactionEntity->getOrder()->getSalesChannelId(),
            $context
        );
    }

    /**
     * @param PaynlTransactionEntity $paynlTransactionEntity
     * @param Context $context
     * @return bool
     */
    private function checkDoubleOrderTransactions(
        PaynlTransactionEntity $paynlTransactionEntity,
        Context $context
    ): bool {
        $orderId = $paynlTransactionEntity->getOrder()->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::GT => $paynlTransactionEntity->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        ]));

        return (bool)$this->paynlTransactionRepository->search($criteria, $context)->count();
    }

    private function getAllowedTransitions(string $actionName, string $stateMachineId, string $stateMachineStateId): int
    {
        $filter = (new Criteria())->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('actionName', $actionName),
                    new EqualsFilter('stateMachineId', $stateMachineId),
                    new EqualsFilter('fromStateId', $stateMachineStateId),
                    new NotFilter(NotFilter::CONNECTION_AND, [
                        new EqualsFilter('toStateId', $stateMachineStateId),
                    ])
                ]
            ));

        $context = Context::createDefaultContext();

        return $this->stateMachineTransitionRepository->search($filter, $context)->count();
    }

    private function isPaidPartlyToPaidTransition(
        string $transitionName,
        string $orderTransactionId,
        Context $context
    ): bool {
        $transactionCriteria = (new Criteria([$orderTransactionId]))
            ->addAssociation('stateMachineState');
        /** @var null|OrderTransactionEntity $transaction */
        $transaction = $this->orderTransactionRepository->search($transactionCriteria, $context)->first();

        if (empty($transaction)
            || $transitionName !== StateMachineTransitionActions::ACTION_PAID
            || $transaction->getStateMachineState()->getTechnicalName() !== OrderTransactionStates::STATE_PARTIALLY_PAID
        ) {
            return false;
        }

        return true;
    }

    private function isUnprocessedTransactionState(PayOrder $payTransactionStatus): bool
    {
        return empty($this->getOrderActionNameByPaynlTransactionStatusCode($payTransactionStatus->getStatusCode()));
    }

    /**
     * @param string $paynlTransactionId
     * @param int $paynlTransactionStatusCode
     * @param string $orderTransactionTransitionName
     * @param string $stateMachineStateId
     */
    private function updatePaynlTransactionStatus(
        string $paynlTransactionId,
        int $paynlTransactionStatusCode,
        string $orderTransactionTransitionName,
        string $stateMachineStateId = ''
    ): void {
        $updateData['id'] = $paynlTransactionId;
        $updateData['stateId'] = $paynlTransactionStatusCode;

        if (!empty($orderTransactionTransitionName)) {
            $updateData['latestActionName'] = $orderTransactionTransitionName;
        }

        if (!empty($stateMachineStateId)) {
            $updateData['orderStateId'] = $stateMachineStateId;
        }

        $this->paynlTransactionRepository->update([$updateData], Context::createDefaultContext());
    }

    /**
     * @param string $orderTransactionId
     * @param string $orderTransactionTransitionName
     * @return StateMachineStateCollection
     */
    private function manageOrderTransactionStateTransition(
        string $orderTransactionId,
        string $orderTransactionTransitionName
    ): StateMachineStateCollection {
        $context = Context::createDefaultContext();

        if ($this->isPaidPartlyToPaidTransition($orderTransactionTransitionName, $orderTransactionId, $context)) {
            // If the previous state is "paid_partially", "paid" is currently not allowed as direct transition,
            // see https://github.com/shopwareLabs/SwagPayPal/blob/b63efb9/src/Util/PaymentStatusUtil.php#L79
            $this->manageOrderTransactionStateTransition(
                $orderTransactionId,
                StateMachineTransitionActions::ACTION_DO_PAY
            );
        }

        return $this->stateMachineRegistry->transition(
            new Transition(
                OrderTransactionDefinition::ENTITY_NAME,
                $orderTransactionId,
                $orderTransactionTransitionName,
                'stateId'
            ),
            $context
        );
    }

    /**
     * @param string $paynlTransactionId
     * @return PaynlTransactionEntity
     */
    private function getPaynlTransactionEntityByPaynlTransactionId(string $paynlTransactionId): PaynlTransactionEntity
    {
        $criteria = (new Criteria());
        $criteria->addFilter(new EqualsFilter('paynlTransactionId', $paynlTransactionId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('orderTransaction.stateMachineState');
        $criteria->addAssociation('orderTransaction.order');

        return $this->paynlTransactionRepository->search($criteria, Context::createDefaultContext())->first();
    }

    /**
     * @param string $orderId
     * @return PaynlTransactionEntity
     */
    private function getPaynlTransactionEntityByOrderId(string $orderId): PaynlTransactionEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->addAssociation('order');
        $criteria->addAssociation('orderTransaction.stateMachineState');
        $criteria->addAssociation('orderTransaction.order');

        return $this->paynlTransactionRepository->search($criteria, Context::createDefaultContext())->first();
    }

    /**
     * @param int $status
     * @return string
     */
    private function getOrderActionNameByPaynlTransactionStatusCode(int $status): string
    {
        return PaynlTransactionStatusesEnum::STATUSES_ARRAY[$status] ?? '';
    }
}
