<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Exceptions\PayPartsLinkException;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\RouterInterface;

class TransactionLinker
{
    private const LOCK_PREFIX = 'paynl-payparts-link-';
    private const LOCK_TTL = 30.0;

    private OrderTransactionRepositoryInterface $orderTransactionRepository;
    private ProcessingHelper $processingHelper;
    private RouterInterface $router;
    private TransactionLinkValidator $linkValidator;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;

    public function __construct(
        OrderTransactionRepositoryInterface $orderTransactionRepository,
        ProcessingHelper $processingHelper,
        RouterInterface $router,
        TransactionLinkValidator $linkValidator,
        LoggerInterface $logger,
        LockFactory $lockFactory
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->processingHelper = $processingHelper;
        $this->router = $router;
        $this->linkValidator = $linkValidator;
        $this->logger = $logger;
        $this->lockFactory = $lockFactory;
    }

    /**
     * Creates the paynl_transactions record, syncs PAY.nl status to Shopware,
     * then returns the checkout finish URL.
     *
     * The browser calls this after a successful payment. PAY.'s webhook usually arrives a moment earlier,
     * finds no record yet, logs "no local Paynl transaction" and gets retried by PAY. That's expected.
     *
     * @throws PayPartsLinkException only for security / validation failures (403)
     */
    public function link(
        string $paynlTransactionId,
        string $orderTransactionId,
        SalesChannelContext $salesChannelContext
    ): string {
        $orderTransaction = $this->loadOrderTransactionForCustomer(
            $orderTransactionId,
            $salesChannelContext
        );

        $this->linkValidator->validateSecurity($orderTransaction, $salesChannelContext);

        $order = $orderTransaction->getOrder();
        if ($order === null) {
            throw PayPartsLinkException::accessDenied();
        }

        $context = $salesChannelContext->getContext();

        // Check-then-store must not interleave: two parallel requests could otherwise link one PAY. payment
        // to two orders. The second request waits here, then sees the stored row and is rejected.
        $lock = $this->lockFactory->createLock(self::LOCK_PREFIX . $paynlTransactionId, self::LOCK_TTL);
        $lock->acquire(true);

        try {
            $this->linkValidator->validatePaynlTransaction(
                $paynlTransactionId,
                $orderTransaction,
                $salesChannelContext
            );

            if (!$this->linkValidator->isAlreadyLinked($paynlTransactionId, $context)) {
                $this->processingHelper->storePayTransactionData(
                    $orderTransaction,
                    $paynlTransactionId,
                    $context,
                    $this->linkValidator->getStorageWarning($orderTransaction)
                );
            }
        } finally {
            $lock->release();
        }

        $this->syncTransactionStatus($paynlTransactionId, $order->getId(), $orderTransactionId);

        return $this->router->generate(
            'frontend.checkout.finish.page',
            ['orderId' => $order->getId()],
            RouterInterface::ABSOLUTE_URL
        );
    }

    /**
     * Sync PAY.nl status to paynl_transactions and the Shopware order state.
     * We log failures and never throw, because the payment already succeeded.
     */
    private function syncTransactionStatus(
        string $paynlTransactionId,
        string $orderId,
        string $orderTransactionId
    ): void {
        $notifyResult = $this->processingHelper->processNotify($paynlTransactionId);

        if (($notifyResult['result'] ?? false) === true) {
            $this->logger->info('PAY.Parts transaction status synced after link', [
                'paynlTransactionId' => $paynlTransactionId,
                'orderId' => $orderId,
                'orderTransactionId' => $orderTransactionId,
                'message' => $notifyResult['message'] ?? '',
            ]);

            return;
        }

        $this->logger->warning('PAY.Parts transaction status sync failed after link; webhook may retry', [
            'paynlTransactionId' => $paynlTransactionId,
            'orderId' => $orderId,
            'orderTransactionId' => $orderTransactionId,
            'message' => $notifyResult['message'] ?? 'Unknown error',
        ]);
    }

    /**
     * Loads only transactions owned by the current customer (guest or registered).
     *
     * @throws PayPartsLinkException
     */
    private function loadOrderTransactionForCustomer(
        string              $id,
        SalesChannelContext $salesChannelContext
    ): OrderTransactionEntity {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            throw PayPartsLinkException::accessDenied();
        }

        $criteria = new Criteria([$id]);
        $criteria->addFilter(
            new EqualsFilter('order.orderCustomer.customerId', $customer->getId())
        );
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.deliveries');
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('paymentMethod.translations');
        $criteria->addAssociation('stateMachineState');

        /** @var OrderTransactionEntity|null $transaction */
        $transaction = $this->orderTransactionRepository
            ->search($criteria, $salesChannelContext->getContext())
            ->first();

        if ($transaction === null) {
            throw PayPartsLinkException::accessDenied();
        }

        return $transaction;
    }
}

