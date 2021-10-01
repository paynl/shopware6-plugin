<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Exception;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
    /** @var Api */
    private $paynlApi;
    /** @var EntityRepositoryInterface */
    private $paynlTransactionRepository;
    /** @var EntityRepositoryInterface  */
    private $orderTransactionRepository;
    /** @var StateMachineRegistry */
    private $stateMachineRegistry;

    public function __construct(
        Api $api,
        EntityRepositoryInterface $paynlTransactionRepository,
        EntityRepositoryInterface $orderTransactionRepository,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->paynlApi = $api;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function storePaynlTransactionData(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        string $paynlTransactionId,
        ?Throwable $exception = null
    ): void {
        $shopwarePaymentMethodId = $salesChannelContext->getPaymentMethod()->getId();
        /** @var CustomerEntity $customer */
        $customer = $salesChannelContext->getCustomer();
        $transactionData = [
            'paynlTransactionId' => $paynlTransactionId,
            'customerId' => $customer->getId(),
            'orderId' => $transaction->getOrder()->getId(),
            'orderTransactionId' => $transaction->getOrderTransaction()->getId(),
            'paymentId' => $this->paynlApi->getPaynlPaymentMethodId($shopwarePaymentMethodId),
            'amount' => $transaction->getOrder()->getAmountTotal(),
            'latestActionName' => StateMachineTransitionActions::ACTION_REOPEN,
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
            'orderStateId' => $transaction->getOrder()->getStateId(),
            // TODO: check sComment from shopware5 plugin
            'dispatch' => $salesChannelContext->getShippingMethod()->getId(),
            'exception' => (string)$exception,
        ];
        $this->paynlTransactionRepository->create([$transactionData], $salesChannelContext->getContext());
    }

    public function getPaynlApiTransaction(string $transactionId): ResultTransaction
    {
        return $this->paynlApi->getTransaction($transactionId);
    }

    /**
     * @param string $paynlTransactionId
     * @return string
     */
    public function notifyActionUpdateTransactionByPaynlTransactionId(string $paynlTransactionId): string
    {
        $paynlApiTransaction = $this->getPaynlApiTransaction($paynlTransactionId);
        $paynlTransactionEntity = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);

        $this->updateTransactionStatus($paynlTransactionEntity, $paynlApiTransaction);
        $apiTransactionData = $paynlApiTransaction->getData();

        return sprintf(
            'TRUE| Status updated to: %s (%s) orderNumber: %s',
            $apiTransactionData['paymentDetails']['stateName'],
            $apiTransactionData['paymentDetails']['state'],
            $apiTransactionData['paymentDetails']['orderNumber']
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
        $paynlApiTransaction = $this->getPaynlApiTransaction($paynlTransactionEntity->getPaynlTransactionId());

        $this->updateTransactionStatus($paynlTransactionEntity, $paynlApiTransaction);
    }

    /**
     * @param string $paynlTransactionId
     * @return void
     * @throws Exception
     */
    public function refundActionUpdateTransactionByTransactionId(string $paynlTransactionId): void
    {
        /** @var PaynlTransactionEntity $transactionEntity */
        $paynlTransactionEntity = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);
        $paynlApiTransaction = $this->getPaynlApiTransaction($paynlTransactionId);

        $this->updateTransactionStatus($paynlTransactionEntity, $paynlApiTransaction);
    }

    /**
     * @param string $paynlTransactionId
     * @return string
     */
    public function processNotify(string $paynlTransactionId): string
    {
        try {
            return $this->notifyActionUpdateTransactionByPaynlTransactionId($paynlTransactionId);
        } catch (Throwable $e) {
            return sprintf(
                'FALSE| Error "%s" in file %s',
                $e->getMessage(),
                $e->getFile()
            );
        }
    }

    /**
     * @param string $paynlId
     * @param string $paynlTransactionId
     * @param string $currentActionName
     * @return void
     * @throws \Paynl\Error\Error
     */
    public function processChangePaynlStatus(string $paynlId, string $paynlTransactionId, string $currentActionName): void
    {
        $paynlTransaction = $this->paynlApi->getTransaction($paynlTransactionId);
        if ($paynlTransaction->isBeingVerified()
            && $currentActionName === StateMachineTransitionActions::ACTION_PAID) {
            $this->updatePaynlTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_PAID,
                $currentActionName
            );

            $paynlTransaction->approve();

            return;
        }

        if ($paynlTransaction->isBeingVerified()
            && $currentActionName === StateMachineTransitionActions::ACTION_CANCEL
        ) {
            $this->updatePaynlTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_CANCEL,
                $currentActionName
            );

            $paynlTransaction->decline();

            return;
        }

        if ($paynlTransaction->isAuthorized() && $currentActionName === StateMachineTransitionActions::ACTION_PAID) {
            $this->updatePaynlTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_PAID,
                $currentActionName
            );

            $paynlTransaction->capture();

            return;
        }

        if ($paynlTransaction->isAuthorized() && $currentActionName === StateMachineTransitionActions::ACTION_CANCEL) {
            $this->updatePaynlTransactionStatus(
                $paynlId,
                PaynlTransactionStatusesEnum::STATUS_CANCEL,
                $currentActionName
            );

            $paynlTransaction->void();

            return;
        }
    }

    /**
     * @param PaynlTransactionEntity $paynlTransactionEntity
     * @param ResultTransaction $paynlApiTransaction
     */
    private function updateTransactionStatus(
        PaynlTransactionEntity $paynlTransactionEntity,
        ResultTransaction $paynlApiTransaction
    ): void {
        $paynlTransactionStatusCode = $this->getTransactionStatusFromPaynlApiTransaction($paynlApiTransaction);
        $orderTransactionTransitionName =
            $this->getOrderActionNameByPaynlTransactionStatusCode($paynlTransactionStatusCode);

        /** @var OrderTransactionEntity $orderTransaction */
        $orderTransaction = $paynlTransactionEntity->getOrderTransaction();
        $stateMachineStateId = $orderTransaction->getStateId();
        $currentTransactionState = $orderTransaction->getStateMachineState()->getTechnicalName();

        if (
            !empty($orderTransactionTransitionName)
            && $orderTransactionTransitionName !== $currentTransactionState
        ) {
            $orderTransactionId = $paynlTransactionEntity->get('orderTransactionId') ?: '';
            $stateMachine = $this->manageOrderTransactionStateTransition(
                $orderTransactionId,
                $orderTransactionTransitionName
            );

            /** @var StateMachineStateEntity $stateMachineStateEntity */
            $stateMachineStateEntity = $stateMachine->get('toPlace');
            $stateMachineStateId = $stateMachineStateEntity->getUniqueIdentifier();
        }

        $this->updatePaynlTransactionStatus(
            $paynlTransactionEntity->getId(),
            $paynlTransactionStatusCode,
            $orderTransactionTransitionName,
            $stateMachineStateId
        );
    }

    /**
     * @param ResultTransaction $paynlApiTransaction
     * @return int
     */
    private function getTransactionStatusFromPaynlApiTransaction(ResultTransaction $paynlApiTransaction): int
    {
        try {
            return (int)($paynlApiTransaction->getStatus()->getData()['paymentDetails']['state'] ?? 0);
        } catch (Throwable $exception) {
            return 0;
        }
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
        $updateData['latestActionName'] = $orderTransactionTransitionName;
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

        $transactionCriteria = (new Criteria([$orderTransactionId]))
            ->addAssociation('stateMachineState');
        /** @var null|OrderTransactionEntity $transaction */
        $transaction = $this->orderTransactionRepository->search($transactionCriteria, $context)->first();

        if (!empty($transaction)
            && $orderTransactionTransitionName === StateMachineTransitionActions::ACTION_PAID
            && $transaction->getStateMachineState()->getTechnicalName() === OrderTransactionStates::STATE_PARTIALLY_PAID
        ) {
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
