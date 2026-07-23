<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Service\OrderService;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Response\OrderCreationResult;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class OrderCreationService
{
    private OrderService $orderService;
    private RouterInterface $router;

    public function __construct(OrderService $orderService, RouterInterface $router)
    {
        $this->orderService = $orderService;
        $this->router       = $router;
    }

    /**
     * Creates a Shopware order from the current cart and returns identifiers
     * needed by the frontend to link the PAY.Parts transaction afterwards.
     *
     * @throws \Exception when the order or its transaction cannot be retrieved
     */
    public function createFromContext(SalesChannelContext $context): OrderCreationResult
    {
        $order = $this->orderService->createOrder(new DataBag(), $context);

        /** @var OrderTransactionCollection $transactions */
        $transactions = $order->getTransactions();
        $transaction  = $transactions->first();

        if ($transaction === null) {
            throw new \RuntimeException(
                sprintf('No transaction found for order "%s".', $order->getId())
            );
        }

        $finishUrl = $this->router->generate(
            'frontend.checkout.finish.page',
            ['orderId' => $order->getId()],
            RouterInterface::ABSOLUTE_URL
        );

        return new OrderCreationResult(
            $order->getId(),
            $transaction->getId(),
            $finishUrl
        );
    }
}
