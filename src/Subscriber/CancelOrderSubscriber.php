<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use PaynlPayment\Shopware6\Service\PaynlTransaction\PaynlTransactionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CancelOrderSubscriber implements EventSubscriberInterface
{
    /** @var Config */
    private $config;
    /** @var OrderRepositoryInterface */
    protected $orderRepository;
    /** @var PaynlTransactionService */
    private $paynlTransactionService;
    /** @var Api */
    private $api;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        Config $config,
        OrderRepositoryInterface $orderRepository,
        PaynlTransactionService $paynlTransactionService,
        Api $api,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->paynlTransactionService = $paynlTransactionService;
        $this->api = $api;
        $this->logger = $logger;
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order.state_changed' => ['onOrderStateChanges']
        ];
    }

    public function onOrderStateChanges(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $transitionName = $event->getTransition()->getTransitionName();
        if ($transitionName !== StateMachineTransitionActions::ACTION_CANCEL) {
            return;
        }

        $context = $event->getContext();
        $order = $this->orderRepository->getOrderById($event->getTransition()->getEntityId(), $context);
        if (!$this->config->isAutomaticShipping($order->getSalesChannelId())) {
            return;
        }

        $paynlTransaction = $this->paynlTransactionService->getPayTransactionByOrderId($order->getId(), $context);
        if (!$paynlTransaction instanceof PaynlTransactionEntity
            || $paynlTransaction->getStateId() != PaynlTransactionStatusesEnum::STATUS_AUTHORIZE
        ) {
            return;
        }

        try {
            $this->logger->info('Starting void PAY. transaction ' . $paynlTransaction->getPaynlTransactionId(), [
                'salesChannel' => $order->getSalesChannel() ? $order->getSalesChannel()->getName() : '',
            ]);

            $this->api->void($paynlTransaction->getPaynlTransactionId(), $order->getSalesChannelId());
        } catch (PaynlTransactionException $exception) {
            $this->logger->error('Error on voiding PAY. transaction ' . $paynlTransaction->getPaynlTransactionId(), [
                'exception' => $exception->getMessage()
            ]);
        }
    }
}
