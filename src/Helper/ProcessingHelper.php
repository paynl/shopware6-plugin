<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Exception;
use PayNL\Sdk\Exception\PayException;
use PayNL\Sdk\Model\Pay\PayOrder;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Enums\StateMachineStateEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use PaynlPayment\Shopware6\Repository\StateMachineState\StateMachineStateRepositoryInterface;
use PaynlPayment\Shopware6\Repository\StateMachineTransition\StateMachineTransitionRepositoryInterface;
use PaynlPayment\Shopware6\Service\Order\OrderStatusUpdater;
use PaynlPayment\Shopware6\ValueObjects\Event\OrderReturnWrittenPayload;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use Throwable;

class ProcessingHelper
{
    private Config $config;
    private Api $payAPI;
    private LoggerInterface $logger;
    private PaynlTransactionsRepositoryInterface $payTransactionRepository;
    private OrderTransactionRepositoryInterface $orderTransactionRepository;
    private StateMachineStateRepositoryInterface $stateMachineStateRepository;
    private StateMachineTransitionRepositoryInterface $stateMachineTransitionRepository;
    private StateMachineRegistry $stateMachineRegistry;
    private OrderStatusUpdater $orderStatusUpdater;

    public function __construct(
        Config $config,
        Api $api,
        LoggerInterface $logger,
        PaynlTransactionsRepositoryInterface $payTransactionRepository,
        OrderTransactionRepositoryInterface $orderTransactionRepository,
        StateMachineStateRepositoryInterface $stateMachineStateRepository,
        StateMachineTransitionRepositoryInterface $stateMachineTransitionRepository,
        StateMachineRegistry $stateMachineRegistry,
        OrderStatusUpdater $orderStatusUpdater
    ) {
        $this->config = $config;
        $this->payAPI = $api;
        $this->logger = $logger;
        $this->payTransactionRepository = $payTransactionRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineStateRepository = $stateMachineStateRepository;
        $this->stateMachineTransitionRepository = $stateMachineTransitionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->orderStatusUpdater = $orderStatusUpdater;
    }

    /** @throws PaynlPaymentException */
    public function storePayTransactionData(
        OrderTransactionEntity $orderTransaction,
        string $payTransactionId,
        Context $context,
        ?Throwable $exception = null
    ): void {
        $order = $orderTransaction->getOrder();
        $paymentId = $this->payAPI->getPaynlPaymentMethodIdFromShopware($orderTransaction);

        /** @var CustomerEntity $customer */
        $customer = $order->getOrderCustomer()->getCustomer();

        $transactionData = [
            'paynlTransactionId' => $payTransactionId,
            'customerId' => $customer->getId(),
            'orderId' => $order->getId(),
            'orderTransactionId' => $orderTransaction->getId(),
            'paymentId' => $paymentId,
            'amount' => $order->getAmountTotal(),
            'latestActionName' => StateMachineTransitionActions::ACTION_REOPEN,
            'currency' => $order->getCurrency()->getIsoCode(),
            'orderStateId' => $order->getStateId(),
            'dispatch' => $order->getDeliveries()->first()->getShippingMethodId(),
            'exception' => (string)$exception,
        ];

        $this->payTransactionRepository->create([$transactionData], $context);
    }

    /**
     * @throws PayException
     * @throws Exception
     */
    public function notifyActionUpdateTransactionByPayTransactionId(string $paynlTransactionId): string
    {
        $paynlTransactionEntity = $this->getPayTransactionEntityByPayTransactionId($paynlTransactionId);

        $payTransactionStatus = $this->payAPI->getTransactionStatus(
            $paynlTransactionId,
            $paynlTransactionEntity->getOrder()->getSalesChannelId()
        );

        if ($this->checkDoubleOrderTransactions($paynlTransactionEntity, Context::createDefaultContext())) {
            $this->updateOldPayTransactionStatus($paynlTransactionEntity, $payTransactionStatus);

            return "TRUE| The current transaction's commands are ignored due to a later completed transaction.";
        }

        $transitionName = $this->getOrderActionNameByPayTransactionStatusCode($payTransactionStatus->getStatusCode());

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
        PaynlTransactionEntity $payTransaction,
        PayOrder $payTransactionStatus
    ): void {
        $transitionName = $this->getOrderActionNameByPayTransactionStatusCode($payTransactionStatus->getStatusCode());

        $criteria = new Criteria();
        $stateMachineId = $payTransaction->getOrderTransaction()->getStateMachineState()->getStateMachineId();
        $fromStateId = $payTransaction->getOrderTransaction()->getStateMachineState()->getId();
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

        $this->updatePayTransactionStatus(
            $payTransaction->getId(),
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
        $payTransactionEntity = $this->getPayTransactionEntityByOrderId($orderId);

        $paynlTransactionId = $payTransactionEntity->getPaynlTransactionId();
        $salesChannelId = $payTransactionEntity->getOrder()->getSalesChannelId();

        $payTransactionStatus = $this->payAPI->getTransactionStatus($paynlTransactionId, $salesChannelId);
        $transitionName = $this->getOrderActionNameByPayTransactionStatusCode($payTransactionStatus->getStatusCode());

        $this->updateTransactionStatus($payTransactionEntity, $transitionName, $payTransactionStatus->getStatusCode());

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
        $paynlTransactionEntity = $this->getPayTransactionEntityByPayTransactionId($paynlTransactionId);
        $salesChannelId = $paynlTransactionEntity->getOrder()->getSalesChannelId();

        $payTransactionStatus = $this->payAPI->getTransactionStatus($paynlTransactionId, $salesChannelId);
        $transitionName = $this->getOrderActionNameByPayTransactionStatusCode($payTransactionStatus->getStatusCode());

        $this->updateTransactionStatus($paynlTransactionEntity, $transitionName, $payTransactionStatus->getStatusCode());
    }

    public function refund(OrderReturnWrittenPayload $orderReturnPayload, Context $context): void
    {
        if (!$orderReturnPayload->getOrderId() || !$orderReturnPayload->getAmountTotal()) {
            $this->logger->error('Order return: orderId or amountTotal is empty', [
                'orderId' => $orderReturnPayload->getOrderId(),
                'amountTotal' => $orderReturnPayload->getAmountTotal(),
            ]);

            return;
        }

        try {
            $payTransaction = $this->getPayTransactionEntityByOrderId($orderReturnPayload->getOrderId());
            $order = $payTransaction->getOrder();

            if (!$this->config->isNativeShopwareRefundAllowed($order->getSalesChannelId())) {
                return;
            }

            $orderReturnState = $this->stateMachineStateRepository->findByStateId($orderReturnPayload->getStateId(), $context);
            if (!$orderReturnState || !in_array($orderReturnState->getTechnicalName(), [StateMachineStateEnum::STATE_COMPLETED, StateMachineStateEnum::STATE_DONE])) {
                $this->logger->error('Order return: order return state does not match DONE', [
                    'stateTechnicalName' => $orderReturnState ? $orderReturnState->getTechnicalName() : null,
                ]);

                return;
            }

            if ($payTransaction->getStateId() === (string) PaynlTransactionStatusesEnum::STATUS_REFUND) {
                $this->logger->warning('Order return: PAY transaction is refunded already', [
                    'payTransactionId' => $payTransaction->getPaynlTransactionId(),
                    'payTransactionStateId' => $payTransaction->getStateId(),
                    'orderNumber' => $order->getOrderNumber(),
                ]);

                return;
            }

            $this->logger->info('Order return: starting refunding PAY. transaction ' . $payTransaction->getPaynlTransactionId(), [
                'orderNumber' => $order->getOrderNumber(),
                'amount' => $orderReturnPayload->getAmountTotal(),
            ]);

            try {
                $this->payAPI->refund(
                    $payTransaction->getPaynlTransactionId(),
                    $orderReturnPayload->getAmountTotal(),
                    $order->getSalesChannelId(),
                    (string)$orderReturnPayload->getInternalComment()
                );
            } catch (Exception $exception) {
                $this->payTransactionRepository->update([[
                    'id' => $payTransaction->getPaynlTransactionId(),
                    'exception' => sprintf('Order return: %s', (string) $exception)
                ]], $context);

                throw $exception;
            }

            $this->refundActionUpdateTransactionByTransactionId($payTransaction->getPaynlTransactionId());
        } catch (Exception $exception) {
            $this->logger->error('Order return: error on refunding PAY. transaction', [
                'payTransactionId' => isset($payTransaction) ? $payTransaction->getPaynlTransactionId() : '',
                'orderNumber' => isset($order) ? $order->getOrderNumber() : null,
                'exception' => $exception->getMessage()
            ]);
        }
    }

    /** @throws Exception */
    public function instorePaymentUpdateState(
        string $paynlTransactionId,
        string $transitionName,
        int $paynlTransactionStatusCode
    ): void {
        /** @var PaynlTransactionEntity $transactionEntity */
        $paynlTransactionEntity = $this->getPayTransactionEntityByPayTransactionId($paynlTransactionId);

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

            return 'FALSE| Error';
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
        $payTransactionStatus = $this->payAPI->getTransactionStatus($paynlTransactionId, $salesChannelId);
        $paynlTransactionEntity = $this->getPayTransactionEntityByPayTransactionId($paynlTransactionId);

        if ($payTransactionStatus->isBeingVerified()
            && $currentActionName === StateMachineTransitionActions::ACTION_PAID
        ) {
            $this->updatePayTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_PAID,
                $currentActionName
            );

            $this->payAPI->approve($paynlTransactionId, $salesChannelId);

        } elseif ($payTransactionStatus->isBeingVerified()
            && $currentActionName === StateMachineTransitionActions::ACTION_CANCEL
        ) {
            $this->updatePayTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_CANCEL,
                $currentActionName
            );

            $this->payAPI->decline($paynlTransactionId, $salesChannelId);

        } elseif ($payTransactionStatus->isAuthorized()
            && $currentActionName === StateMachineTransitionActions::ACTION_PAID
        ) {
            $this->updatePayTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_PAID,
                $currentActionName
            );

            $this->payAPI->capture($paynlTransactionId, null, $salesChannelId);

        } elseif ($payTransactionStatus->isAuthorized()
            && $currentActionName === StateMachineTransitionActions::ACTION_CANCEL
        ) {
            $this->updatePayTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_CANCEL,
                $currentActionName
            );

            $this->payAPI->void($paynlTransactionId, $salesChannelId);
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

    /** @throws PaynlPaymentException */
    public function getOrderTransaction(string $orderTransactionId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.addresses');
        $criteria->addAssociation('order.addresses.country');
        $criteria->addAssociation('order.billingAddress');
        $criteria->addAssociation('order.billingAddress.country');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.orderCustomer.customer.salutation');
        $criteria->addAssociation('order.orderCustomer.customer.defaultBillingAddress');
        $criteria->addAssociation('order.orderCustomer.customer.defaultBillingAddress.country');
        $criteria->addAssociation('order.orderCustomer.customer.defaultShippingAddress');
        $criteria->addAssociation('order.orderCustomer.customer.defaultShippingAddress.country');
        $criteria->addAssociation('order.orderCustomer.salutation');
        $criteria->addAssociation('order.language');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.salesChannel');
        $criteria->addAssociation('order.deliveries.shippingOrderAddress');
        $criteria->addAssociation('order.deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.transactions.stateMachineState');
        $criteria->addAssociation('order.transactions.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('paymentMethod.appPaymentMethod.app');
        $criteria->getAssociation('order.transactions')->addSorting(new FieldSorting('createdAt'));
        $criteria->addSorting(new FieldSorting('createdAt'));

        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();

        if (!$orderTransaction) {
            throw new PaynlPaymentException('Invalid transaction: ' . $orderTransactionId);
        }

        return $orderTransaction;
    }

    /**
     * @param PaynlTransactionEntity $paynlTransactionEntity
     * @param string $transitionName
     * @param int $paynlTransactionStatusCode
     * @return void
     * @throws Exception
     */
    private function updateTransactionStatus(
        PaynlTransactionEntity $payTransactionEntity,
        string $transitionName,
        int $payTransactionStatusCode
    ): void {
        $orderTransaction = $payTransactionEntity->getOrderTransaction();
        $orderTransactionId = $payTransactionEntity->get('orderTransactionId') ?: '';
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
            && ($transitionName !== $payTransactionEntity->getLatestActionName())
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

        $this->updatePayTransactionStatus(
            $payTransactionEntity->getId(),
            $payTransactionStatusCode,
            $transitionName,
            $stateMachineStateId
        );

        $this->orderStatusUpdater->updateOrderStatus(
            $payTransactionEntity->getOrder(),
            $payTransactionStatusCode,
            $payTransactionEntity->getOrder()->getSalesChannelId(),
            $context
        );
    }

    private function checkDoubleOrderTransactions(
        PaynlTransactionEntity $payTransactionEntity,
        Context $context
    ): bool {
        $orderId = $payTransactionEntity->getOrder()->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::GT => $payTransactionEntity->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        ]));

        return (bool)$this->payTransactionRepository->search($criteria, $context)->count();
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
        return empty($this->getOrderActionNameByPayTransactionStatusCode($payTransactionStatus->getStatusCode()));
    }

    private function updatePayTransactionStatus(
        string $payTransactionId,
        int $payTransactionStatusCode,
        string $orderTransactionTransitionName,
        string $stateMachineStateId = ''
    ): void {
        $updateData['id'] = $payTransactionId;
        $updateData['stateId'] = $payTransactionStatusCode;

        if (!empty($orderTransactionTransitionName)) {
            $updateData['latestActionName'] = $orderTransactionTransitionName;
        }

        if (!empty($stateMachineStateId)) {
            $updateData['orderStateId'] = $stateMachineStateId;
        }

        $this->payTransactionRepository->update([$updateData], Context::createDefaultContext());
    }

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

    private function getPayTransactionEntityByPayTransactionId(string $payTransactionId): PaynlTransactionEntity
    {
        $criteria = (new Criteria());
        $criteria->addFilter(new EqualsFilter('paynlTransactionId', $payTransactionId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('orderTransaction.stateMachineState');
        $criteria->addAssociation('orderTransaction.order');

        return $this->payTransactionRepository->search($criteria, Context::createDefaultContext())->first();
    }

    /** @throws PaynlTransactionException */
    private function getPayTransactionEntityByOrderId(string $orderId): PaynlTransactionEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->addAssociation('order');
        $criteria->addAssociation('orderTransaction.stateMachineState');
        $criteria->addAssociation('orderTransaction.order');

        $payTransaction = $this->payTransactionRepository->search($criteria, Context::createDefaultContext())->first();

        if (!($payTransaction instanceof PaynlTransactionEntity)) {
            throw PaynlTransactionException::notFoundByOrderError($orderId);
        }

        return $payTransaction;
    }

    private function getOrderActionNameByPayTransactionStatusCode(int $status): string
    {
        return PaynlTransactionStatusesEnum::STATUSES_ARRAY[$status] ?? '';
    }
}
