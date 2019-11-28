<?php declare(strict_types=1);

namespace PaynlPayment\Helper;

use Exception;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use PaynlPayment\Components\Api;
use PaynlPayment\Entity\PaynlTransactionEntity;
use PaynlPayment\Entity\PaynlTransactionEntity as PaynlTransaction;
use phpDocumentor\Reflection\Types\Mixed;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
            // TODO: check sComment from shopware5 plugin
            'dispatch' => $salesChannelContext->getShippingMethod()->getId(),
            'exception' => (string)$exception,
        ];
        $this->paynlTransactionRepository->create([$transactionData], $salesChannelContext->getContext());
    }

    /**
     * @return mixed
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
            $status = 0;
            $orderActionName = '';
            if ($apiTransaction->isBeingVerified()) {
                $status = PaynlTransactionStatusesEnum::STATUS_PENDING;
            } elseif ($apiTransaction->isPending()) {
                $status = PaynlTransactionStatusesEnum::STATUS_PENDING;
            } elseif ($apiTransaction->isPartiallyRefunded()) {
                $status = PaynlTransactionStatusesEnum::STATUS_PARTIAL_REFUND;
                $orderActionName = StateMachineTransitionActions::ACTION_REFUND_PARTIALLY;
            } elseif ($apiTransaction->isRefunded()) {
                $status = PaynlTransactionStatusesEnum::STATUS_REFUND;
                $orderActionName = StateMachineTransitionActions::ACTION_REFUND;
            } elseif ($apiTransaction->isAuthorized()) {
                $status = PaynlTransactionStatusesEnum::STATUS_AUTHORIZED;
            } elseif ($apiTransaction->isPaid()) {
                $status = PaynlTransactionStatusesEnum::STATUS_PAID;
                $orderActionName = StateMachineTransitionActions::ACTION_PAY;
            } elseif ($apiTransaction->isCanceled()) {
                $status = $status = PaynlTransactionStatusesEnum::STATUS_CANCEL;
                $orderActionName = StateMachineTransitionActions::ACTION_CANCEL;
            }

            $this->setPaynlStatus($paynlTransactionId, $context, $status, $orderActionName);

            if (!empty($orderActionName)) {
                $orderTransactionId = $paynlTransaction->get('orderTransactionId');
                $this->manageOrderStateTransition($orderTransactionId, $orderActionName, $context);
            }

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
     * @param string $orderStatus
     */
    public function setPaynlStatus(string $paynlTransactionId, Context $context, int $status, string $orderStatus): void
    {
        $this->paynlTransactionRepository->update(
            [
                [
                    'id' => $paynlTransactionId,
                    'stateId' => $status,
                    'orderStateName' => $orderStatus,
                ]
            ],
            $context
        );
    }

    /**
     * @param string $paynlTransactionId
     * @return mixed
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
     * @return string|void
     */
    public function manageOrderStateTransition(string $orderTransactionId, string $actionName, Context $context)
    {
        try {
            $this->stateMachineRegistry->transition(
                new Transition(
                    OrderTransactionDefinition::ENTITY_NAME,
                    $orderTransactionId,
                    $actionName,
                    'stateId'
                ),
                $context
            );
        } catch (IllegalTransitionException $exception) {
            return $exception->getMessage();
        }
    }
}
