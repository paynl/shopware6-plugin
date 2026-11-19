<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Exceptions\PayPartsOrderException;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use PaynlPayment\Shopware6\Service\CartServiceInterface;
use PaynlPayment\Shopware6\Service\OrderService;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Response\OrderCreationResult;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class OrderCreationService
{
    private OrderService $orderService;
    private RouterInterface $router;
    private CartServiceInterface $cartService;
    private PaymentMethodRepositoryInterface $paymentMethodRepository;

    public function __construct(
        OrderService $orderService,
        RouterInterface $router,
        CartServiceInterface $cartService,
        PaymentMethodRepositoryInterface $paymentMethodRepository
    ) {
        $this->orderService = $orderService;
        $this->router = $router;
        $this->cartService = $cartService;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * Resolves and validates the card payment method and makes it the selected one (persisted),
     * so the order is created with the method that is actually charged. Used when the customer presses Pay.
     *
     * @throws PayPartsOrderException
     */
    public function switchToCardMethod(SalesChannelContext $context, ?string $paymentMethodId = null): SalesChannelContext
    {
        $paymentMethod = $this->resolveCardMethod($context, $paymentMethodId);

        if ($context->getPaymentMethod()->getId() !== $paymentMethod->getId()) {
            $context = $this->cartService->updatePaymentMethod($context, $paymentMethod->getId());
        }

        return $context;
    }

    /**
     * Same as switchToCardMethod, but on a copy of the context: the customer's selected payment method
     * stays untouched. Used for the session amount on page load and for the amount check before the
     * order is created, so the native "Submit order" button keeps paying with the customer's own method.
     *
     * @throws PayPartsOrderException
     */
    public function withCardMethod(SalesChannelContext $context, ?string $paymentMethodId = null): SalesChannelContext
    {
        $paymentMethod = $this->resolveCardMethod($context, $paymentMethodId);

        if ($context->getPaymentMethod()->getId() === $paymentMethod->getId()) {
            return $context;
        }

        $cardContext = clone $context;
        $cardContext->assign(['paymentMethod' => $paymentMethod]);

        return $cardContext;
    }

    /** @throws PayPartsOrderException */
    private function resolveCardMethod(SalesChannelContext $context, ?string $paymentMethodId): PaymentMethodEntity
    {
        // The storefront always sends the card method it was rendered for.
        if ($paymentMethodId === null) {
            throw PayPartsOrderException::invalidPaymentMethod();
        }

        try {
            $paymentMethod = $this->paymentMethodRepository
                ->getPaymentMethodById($paymentMethodId, $context->getContext());
        } catch (EntityNotFoundException $exception) {
            throw PayPartsOrderException::paymentMethodNotFound($paymentMethodId);
        }

        if (!$this->paymentMethodRepository->isPayPartsCardPaymentMethod($paymentMethod)) {
            throw PayPartsOrderException::invalidPaymentMethod();
        }

        return $paymentMethod;
    }

    /**
     * Creates a Shopware order from the current cart and returns identifiers
     * needed by the frontend to link the PAY.Parts transaction afterwards.
     * The cart total must equal the amount the PAY.Parts session was created with,
     * otherwise PAY.nl would charge a different amount than the order total.
     *
     * @throws PayPartsOrderException
     */
    public function createFromContext(
        SalesChannelContext $context,
        ?string $paymentMethodId = null,
        ?int $expectedAmountCents = null,
        ?string $expectedCurrency = null,
        ?DataBag $orderData = null
    ): OrderCreationResult {
        // Check the amount on a copy first: a refused order must not change the customer's selected method.
        $cardContext = $this->withCardMethod($context, $paymentMethodId);

        $cart = $this->cartService->getCalculatedMainCart($cardContext);
        PayPartsOrderException::assertAmountMatches(
            (int) round($cart->getPrice()->getTotalPrice() * 100),
            $cardContext->getCurrency()->getIsoCode(),
            $expectedAmountCents,
            $expectedCurrency
        );

        // The order is really created now, so persist the card method (the order is created with the context's method).
        $originalMethodId = $context->getPaymentMethod()->getId();
        $context = $this->switchToCardMethod($context, $cardContext->getPaymentMethod()->getId());

        try {
            // Real confirm-form values (tos, revocation, comment...): Shopware validates them like a normal checkout.
            $order = $this->orderService->createOrder($orderData ?? new DataBag(), $context);
        } catch (Throwable $exception) {
            // Nothing was ordered: give the customer their own payment method back.
            if ($originalMethodId !== $context->getPaymentMethod()->getId()) {
                $this->cartService->updatePaymentMethod($context, $originalMethodId);
            }

            throw $exception instanceof ConstraintViolationException
                ? PayPartsOrderException::orderDataInvalid($exception)
                : PayPartsOrderException::orderCreationFailed($exception);
        }

        /** @var OrderTransactionCollection $transactions */
        $transactions = $order->getTransactions();
        $transaction  = $transactions->first();

        if ($transaction === null) {
            throw PayPartsOrderException::transactionNotFound($order->getId());
        }

        $editOrderUrl = $this->router->generate(
            'frontend.account.edit-order.page',
            ['orderId' => $order->getId()],
            RouterInterface::ABSOLUTE_URL
        );

        return new OrderCreationResult(
            $order->getId(),
            $transaction->getId(),
            $editOrderUrl
        );
    }
}
