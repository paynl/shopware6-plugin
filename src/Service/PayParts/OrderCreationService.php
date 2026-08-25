<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use Exception;
use PaynlPayment\Shopware6\Exceptions\PayPartsOrderException;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use PaynlPayment\Shopware6\Service\CartServiceInterface;
use PaynlPayment\Shopware6\Service\OrderService;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Response\OrderCreationResult;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

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
     * @throws PayPartsOrderException
     */
    public function createFromContext(SalesChannelContext $context, ?string $paymentMethodId = null): OrderCreationResult
    {
        if ($paymentMethodId === null) {
            try {
                $paymentMethodId = $this->paymentMethodRepository
                    ->getActivePayPartsCardMethodId($context->getContext());
            } catch (Exception $exception) {
                throw PayPartsOrderException::cardMethodNotFound();
            }
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

        if ($context->getPaymentMethod()->getId() !== $paymentMethodId) {
            $context = $this->cartService->updatePaymentMethod($context, $paymentMethodId);
        }

        try {
            $order = $this->orderService->createOrder(new DataBag(['tos' => '1']), $context);
        } catch (Throwable $exception) {
            throw PayPartsOrderException::orderCreationFailed($exception);
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
