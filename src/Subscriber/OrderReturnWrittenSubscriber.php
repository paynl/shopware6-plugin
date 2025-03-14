<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use Exception;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Repository\OrderReturn\OrderReturnRepositoryInterface;
use PaynlPayment\Shopware6\Service\PaynlTransaction\PaynlTransactionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderReturnWrittenSubscriber implements EventSubscriberInterface
{
    private Config $config;
    private LoggerInterface $logger;
    private Api $payAPI;
    private PaynlTransactionService $payTransactionService;

    public function __construct(
        Config $config,
        LoggerInterface $logger,
        Api $payAPI,
        PaynlTransactionService $payTransactionService
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->payAPI = $payAPI;
        $this->payTransactionService = $payTransactionService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'order_return.written' => 'onOrderReturnChanged',
        ];
    }

    public function onOrderReturnChanged(EntityWrittenEvent $event): void
    {
        $this->logger->info('Order return written event', [
            'event' => $event,
            'writtenResult' => $event->getWriteResults() ? $event->getWriteResults()[0] : []
        ]);
    }
}
