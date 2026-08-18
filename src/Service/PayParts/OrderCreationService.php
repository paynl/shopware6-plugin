<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use PaynlPayment\Shopware6\Service\CartServiceInterface;
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
    private CartServiceInterface $cartService;
    private PaymentMethodRepository $paymentMethodRepository;

    public function __construct(
        OrderService $orderService,
        RouterInterface $router,
        CartServiceInterface $cartService,
        PaymentMethodRepository $paymentMethodRepository
    ) {
        $this->orderService = $orderService;
        $this->router = $router;
        $this->cartService = $cartService;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * Creates a Shopware order from the current cart and returns identifiers
     * needed by the frontend to link the PAY.Parts transaction afterwards.
     *
     * @throws \Exception when the order or its transaction cannot be retrieved
     */
    public function createFromContext(SalesChannelContext $context): OrderCreationResult
    {
        $creditCardPaymentMethodId = $this->paymentMethodRepository
            ->getActiveCreditCardID($context->getContext());

        if ($context->getPaymentMethod()->getId() !== $creditCardPaymentMethodId) {
            $context = $this->cartService->updatePaymentMethod($context, $creditCardPaymentMethodId);
        }

        $order = $this->orderService->createOrder(new DataBag(['tos' => '1']), $context);

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

        $editOrderUrl = $this->router->generate(
            'frontend.account.edit-order.page',
            ['orderId' => $order->getId()],
            RouterInterface::ABSOLUTE_URL
        );

        return new OrderCreationResult(
            $order->getId(),
            $transaction->getId(),
            $finishUrl,
            $editOrderUrl
        );
    }
}
