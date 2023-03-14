<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller;

use PaynlPayment\Shopware6\Service\Order\OrderService;
use PaynlPayment\Shopware6\Service\Repository\OrderRepository;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class PaymentController extends AbstractController
{
    private $stateMachineRegistry;
    private $orderRepository;
    private $orderService;

    public function __construct(
        StateMachineRegistry $stateMachineRegistry,
        OrderRepository $orderRepository,
        OrderService $orderService
    ) {
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->orderRepository = $orderRepository;
        $this->orderService = $orderService;
    }

    /**
     * @Route(
     *     "/store-api/paynl/set-payment",
     *     name="store-api.action.paynl.set-payment",
     *     methods={"POST"}
     * )
     */
    public function updatePaymentMethod(Request $request, SalesChannelContext $context): SetPaymentOrderRouteResponse
    {
        $this->setPaymentMethod($request->get('paymentMethodId'), $request->get('orderId'), $context);
        return new SetPaymentOrderRouteResponse();
    }

    private function setPaymentMethod(
        string $paymentMethodId,
        string $orderId,
        SalesChannelContext $salesChannelContext
    ): void {
        $context = $salesChannelContext->getContext();
        $initialState = $this->stateMachineRegistry->getInitialState(OrderTransactionStates::STATE_MACHINE, $context);

        /** @var OrderEntity $order */
        $order = $this->orderRepository->getOrder($orderId, $context, ['transactions']);

        $context->scope(
            Context::SYSTEM_SCOPE,
            function () use ($order, $initialState, $orderId, $paymentMethodId, $context): void {
                if ($order->getTransactions() !== null && $order->getTransactions()->count() >= 1) {
                    foreach ($order->getTransactions() as $transaction) {
                        if ($transaction->getStateMachineState()->getTechnicalName()
                            !== OrderTransactionStates::STATE_CANCELLED) {
                            $this->orderService->orderTransactionStateTransition(
                                $transaction->getId(),
                                'cancel',
                                new ParameterBag(),
                                $context
                            );
                        }
                    }
                }
                $transactionAmount = new CalculatedPrice(
                    $order->getPrice()->getTotalPrice(),
                    $order->getPrice()->getTotalPrice(),
                    $order->getPrice()->getCalculatedTaxes(),
                    $order->getPrice()->getTaxRules()
                );

                $this->orderRepository->update($orderId, [
                    'transactions' => [
                        [
                            'id' => Uuid::randomHex(),
                            'paymentMethodId' => $paymentMethodId,
                            'stateId' => $initialState->getId(),
                            'amount' => $transactionAmount,
                        ],
                    ],
                ], $context);
            }
        );
    }
}
