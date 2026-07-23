<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Routing\RouterInterface;

class TransactionLinker
{
    private OrderTransactionRepositoryInterface $orderTransactionRepository;
    private ProcessingHelper $processingHelper;
    private RouterInterface $router;

    public function __construct(
        OrderTransactionRepositoryInterface $orderTransactionRepository,
        ProcessingHelper $processingHelper,
        RouterInterface $router
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->processingHelper           = $processingHelper;
        $this->router                     = $router;
    }

    /**
     * Creates the paynl_transactions record that links a PAY.Parts transaction
     * to the Shopware order transaction, then returns the checkout finish URL.
     * State transitions are handled by the existing exchange-URL notification flow.
     *
     * @throws \RuntimeException when the order transaction cannot be loaded
     */
    public function link(
        string $paynlTransactionId,
        string $orderTransactionId,
        Context $context
    ): string {
        $orderTransaction = $this->loadOrderTransaction($orderTransactionId, $context);

        $this->processingHelper->storePayTransactionData(
            $orderTransaction,
            $paynlTransactionId,
            $context
        );

        /** @var \Shopware\Core\Checkout\Order\OrderEntity $order */
        $order = $orderTransaction->getOrder();

        return $this->router->generate(
            'frontend.checkout.finish.page',
            ['orderId' => $order->getId()],
            RouterInterface::ABSOLUTE_URL
        );
    }

    private function loadOrderTransaction(string $id, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.deliveries');
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('paymentMethod.translations');

        /** @var OrderTransactionEntity|null $transaction */
        $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($transaction === null) {
            throw new \RuntimeException(
                sprintf('Order transaction "%s" not found.', $id)
            );
        }

        return $transaction;
    }
}
