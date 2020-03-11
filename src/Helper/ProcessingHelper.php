<?php declare(strict_types=1);

namespace PaynlPayment\Helper;

use Exception;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use PaynlPayment\Components\Api;
use PaynlPayment\Entity\PaynlTransactionEntity;
use PaynlPayment\Entity\PaynlTransactionEntity as PaynlTransaction;
use PaynlPayment\Enums\StateMachineStateEnum;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use PaynlPayment\Enums\PaynlTransactionStatusesEnum;

class ProcessingHelper
{
    /** @var Api */
    private $paynlApi;
    /** @var EntityRepositoryInterface */
    private $paynlTransactionRepository;
    /** @var StateMachineRegistry */
    private $stateMachineRegistry;

    public function __construct(
        Api $api,
        EntityRepositoryInterface $paynlTransactionRepository,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->paynlApi = $api;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function storePaynlTransactionData(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        string $paynlTransactionId,
        ?\Throwable $exception = null
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
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
            'orderStateId' => $transaction->getOrder()->getStateId(),
            // TODO: check sComment from shopware5 plugin
            'dispatch' => $salesChannelContext->getShippingMethod()->getId(),
            'exception' => (string)$exception,
        ];
        $this->paynlTransactionRepository->create([$transactionData], $salesChannelContext->getContext());
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return mixed
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function findTransactionByOrderId(string $orderId, Context $context)
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderId', $orderId));

        return $this->paynlTransactionRepository->search($criteria, $context)->first();
    }

    public function getApiTransaction(string $transactionId): ResultTransaction
    {
        return $this->paynlApi->getTransaction($transactionId);
    }

    /**
     * @param PaynlTransactionEntity $paynlTransaction
     * @param Context $context
     * @param bool $isExchange
     * @return string
     */
    public function updateTransaction(PaynlTransaction $paynlTransaction, Context $context, bool $isExchange): string
    {
        try {
            $apiTransaction = $this->getApiTransaction($paynlTransaction->getPaynlTransactionId());
            $paynlTransactionId = $paynlTransaction->getId();
            $status = (int)($apiTransaction->getStatus()->getData()['paymentDetails']['state'] ?? 0);
            $orderActionName = $this->getOrderActionNameByStatus($status);
            $orderStateId = '';
            if (!empty($orderActionName)) {
                $orderTransactionId = $paynlTransaction->get('orderTransactionId') ?: '';

                $stateMachine = $this->manageOrderStateTransition($orderTransactionId, $orderActionName, $context);
                $toPlace = $stateMachine->get('toPlace');
                if ($toPlace instanceof StateMachineStateEntity) {
                    $orderStateId = $toPlace->getUniqueIdentifier();
                }
            }
            $this->setPaynlStatus($paynlTransactionId, $context, $status, $orderStateId);

            $apiTransactionData = $apiTransaction->getData();

            return sprintf(
                "TRUE| Status updated to: %s (%s) orderNumber: %s",
                $apiTransactionData['paymentDetails']['stateName'],
                $apiTransactionData['paymentDetails']['state'],
                $apiTransactionData['paymentDetails']['orderNumber']
            );
        } catch (Exception $e) {
            if ($isExchange) {
                return "FALSE| " . $e->getMessage() . $e->getFile();
            }
        }
        return "FALSE| No action, order was not created";
    }

    /**
     * @param string $paynlTransactionId
     * @param Context $context
     * @param int $status
     * @param string $stateMachineStateId
     */
    private function setPaynlStatus(
        string $paynlTransactionId,
        Context $context,
        int $status,
        string $stateMachineStateId
    ): void {
        $updateData['id'] = $paynlTransactionId;
        $updateData['stateId'] = $status;
        if (!empty($stateMachineStateId)) {
            $updateData['orderStateId'] = $stateMachineStateId;
        }

        $this->paynlTransactionRepository->update([$updateData], $context);
    }

    /**
     * @param string $paynlTransactionId
     * @return string|void
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function processNotify(string $paynlTransactionId)
    {
        $apiTransaction = $this->getApiTransaction($paynlTransactionId);
        if ($apiTransaction->isPending()) {
            return;
        }
        $criteria = (new Criteria());
        $context = Context::createDefaultContext();
        $criteria->addFilter(new EqualsFilter('paynlTransactionId', $paynlTransactionId));
        $entity = $this->paynlTransactionRepository->search($criteria, $context)->first();

        return $this->updateTransaction($entity, $context, true);
    }

    /**
     * @param string $orderTransactionId
     * @param string $actionName
     * @param Context $context
     * @return StateMachineStateCollection
     * @throws Exception
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException
     */
    private function manageOrderStateTransition(
        string $orderTransactionId,
        string $actionName,
        Context $context
    ): StateMachineStateCollection {
        try {
            return $this->stateMachineRegistry->transition(
                new Transition(
                    OrderTransactionDefinition::ENTITY_NAME,
                    $orderTransactionId,
                    $actionName,
                    'stateId'
                ),
                $context
            );
        } catch (IllegalTransitionException $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    /**
     * @param int $status
     * @return string
     */
    private function getOrderActionNameByStatus(int $status): string
    {

        switch ($status) {
            case PaynlTransactionStatusesEnum::STATUS_CANCEL:
                $orderActionName = StateMachineTransitionActions::ACTION_CANCEL;
                break;
            case PaynlTransactionStatusesEnum::STATUS_EXPIRED:
                $orderActionName = StateMachineTransitionActions::ACTION_CANCEL;
                break;
            case PaynlTransactionStatusesEnum::STATUS_REFUNDING:
                $orderActionName = StateMachineTransitionActions::ACTION_REFUND;
                break;
            case PaynlTransactionStatusesEnum::STATUS_REFUND:
                $orderActionName = StateMachineTransitionActions::ACTION_REFUND;
                break;
            case PaynlTransactionStatusesEnum::STATUS_PENDING_20:
                $orderActionName = StateMachineTransitionActions::ACTION_REOPEN;
                break;
            case PaynlTransactionStatusesEnum::STATUS_PENDING_25:
                $orderActionName = StateMachineTransitionActions::ACTION_REOPEN;
                break;
            case PaynlTransactionStatusesEnum::STATUS_PENDING_50:
                $orderActionName = StateMachineTransitionActions::ACTION_REOPEN;
                break;
            case PaynlTransactionStatusesEnum::STATUS_PENDING_90:
                $orderActionName = StateMachineTransitionActions::ACTION_REOPEN;
                break;
            case PaynlTransactionStatusesEnum::STATUS_VERIFY:
                $orderActionName = StateMachineStateEnum::ACTION_VERIFY;
                break;
            case PaynlTransactionStatusesEnum::STATUS_AUTHORIZE:
                $orderActionName = StateMachineStateEnum::ACTION_AUTHORIZE;
                break;
            case PaynlTransactionStatusesEnum::STATUS_PARTLY_CAPTURED:
                $orderActionName = StateMachineStateEnum::ACTION_PARTLY_CAPTURED;
                break;
            case PaynlTransactionStatusesEnum::STATUS_PAID:
                $orderActionName = StateMachineTransitionActions::ACTION_PAY;
                break;
            case PaynlTransactionStatusesEnum::STATUS_PAID_CHECKAMOUNT:
                $orderActionName = StateMachineTransitionActions::ACTION_CANCEL;
                break;
            case PaynlTransactionStatusesEnum::STATUS_FAILURE:
                $orderActionName = StateMachineTransitionActions::ACTION_CANCEL;
                break;
            case PaynlTransactionStatusesEnum::STATUS_DENIED_63:
                $orderActionName = StateMachineTransitionActions::ACTION_CANCEL;
                break;
            case PaynlTransactionStatusesEnum::STATUS_DENIED_64:
                $orderActionName = StateMachineTransitionActions::ACTION_CANCEL;
                break;
            case PaynlTransactionStatusesEnum::STATUS_PARTIAL_REFUND:
                $orderActionName = StateMachineTransitionActions::ACTION_REFUND_PARTIALLY;
                break;
            case PaynlTransactionStatusesEnum::STATUS_PARTIAL_PAYMENT:
                $orderActionName = StateMachineTransitionActions::ACTION_PAY_PARTIALLY;
                break;
            default:
                $orderActionName = '';
                break;
        }

        return $orderActionName;
    }
}
