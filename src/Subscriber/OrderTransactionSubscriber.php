<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PayNL\Sdk\Model\Pay\PayOrder;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Exceptions\PaynlStateChangeException;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

/**
 * Shopware writes the history row first, then the new state, then fires the state events, and
 * nothing undoes those writes when a listener throws. So PAY. is called while the history row is
 * validated: a PAY. refusal aborts the transition before anything is stored. The local PAY. row
 * and the order status are only updated once the transition went through.
 */
final class OrderTransactionSubscriber implements EventSubscriberInterface
{
    private const PAY_ACTIONS = [
        StateMachineTransitionActions::ACTION_PAID,
        StateMachineTransitionActions::ACTION_CANCEL,
    ];

    private PaynlTransactionsRepositoryInterface $paynlTransactionRepository;
    private ProcessingHelper $processingHelper;
    private LoggerInterface $logger;

    /** @var array<string, array{action: string, transaction: PaynlTransactionEntity, payOrder: PayOrder}> */
    private array $acceptedByPay = [];

    public function __construct(
        PaynlTransactionsRepositoryInterface $paynlTransactionRepository,
        ProcessingHelper $processingHelper,
        LoggerInterface $logger
    ) {
        $this->paynlTransactionRepository = $paynlTransactionRepository;
        $this->processingHelper = $processingHelper;
        $this->logger = $logger;
    }

    /**
     * @return array<string, array{0: string, 1: int}|string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'onPreWriteValidation',
            // Before mail and Flow Builder listeners, so they see the order status PAY. reported.
            'state_machine.order_transaction.state_changed' => ['onOrderTransactionStateChanged', 500],
        ];
    }

    public function onPreWriteValidation(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();
        if ($context->hasState(ProcessingHelper::SKIP_PAY_STATUS_CHANGE)) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            if (!$command instanceof InsertCommand
                || $command->getEntityName() !== StateMachineHistoryDefinition::ENTITY_NAME
            ) {
                continue;
            }

            $payload = $command->getPayload();
            $actionName = $payload['action_name'] ?? null;
            if (($payload['entity_name'] ?? null) !== OrderTransactionDefinition::ENTITY_NAME
                || !in_array($actionName, self::PAY_ACTIONS, true)
            ) {
                continue;
            }

            $orderTransactionId = $this->getOrderTransactionId($payload);
            if ($orderTransactionId !== null) {
                $this->requestPayStatusChange($orderTransactionId, $actionName, $context);
            }
        }
    }

    public function onOrderTransactionStateChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $orderTransactionId = $event->getTransition()->getEntityId();
        $accepted = $this->acceptedByPay[$orderTransactionId] ?? null;
        unset($this->acceptedByPay[$orderTransactionId]);

        if ($accepted === null || $accepted['action'] !== $event->getTransition()->getTransitionName()) {
            return;
        }

        $transaction = $accepted['transaction'];
        $this->processingHelper->applyPayNLStatusChange(
            $transaction,
            $accepted['action'],
            $accepted['payOrder'],
            $transaction->getOrder()->getSalesChannelId()
        );
    }

    private function requestPayStatusChange(string $orderTransactionId, string $actionName, Context $context): void
    {
        $transaction = $this->findPaynlTransaction($orderTransactionId, $context);
        if ($transaction === null) {
            return;
        }

        try {
            $payOrder = $this->processingHelper->requestPayNLStatusChange(
                $transaction->getPaynlTransactionId(),
                $actionName,
                $transaction->getOrder()->getSalesChannelId()
            );
        } catch (Throwable $exception) {
            $this->logger->error('PAY. rejected the payment status change. Shopware keeps the current state.', [
                'exception' => $exception,
                'orderTransactionId' => $orderTransactionId,
                'actionName' => $actionName,
            ]);

            throw new PaynlStateChangeException(
                sprintf(
                    'PAY. could not complete "%s". The payment status was not changed. %s',
                    $actionName,
                    $exception->getMessage()
                ),
                $exception
            );
        }

        if ($payOrder !== null) {
            $this->acceptedByPay[$orderTransactionId] = [
                'action' => $actionName,
                'transaction' => $transaction,
                'payOrder' => $payOrder,
            ];
        }
    }

    /**
     * 6.6+ stores the transaction id in referenced_id; 6.5 in the entity_id JSON.
     *
     * @param array<string, mixed> $payload
     */
    private function getOrderTransactionId(array $payload): ?string
    {
        $referencedId = $payload['referenced_id'] ?? null;
        if (is_string($referencedId) && $referencedId !== '') {
            return Uuid::fromBytesToHex($referencedId);
        }

        $entityId = is_string($payload['entity_id'] ?? null) ? json_decode($payload['entity_id'], true) : null;
        $id = is_array($entityId) ? ($entityId['id'] ?? null) : null;

        return is_string($id) && Uuid::isValid($id) ? $id : null;
    }

    private function findPaynlTransaction(string $orderTransactionId, Context $context): ?PaynlTransactionEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderTransactionId', $orderTransactionId))
            ->addAssociation('order.stateMachineState')
            ->setLimit(1);

        $transaction = $this->paynlTransactionRepository->search($criteria, $context)->first();

        return $transaction instanceof PaynlTransactionEntity ? $transaction : null;
    }
}
