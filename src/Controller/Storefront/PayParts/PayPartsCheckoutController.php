<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\PayParts;

use PaynlPayment\Shopware6\Service\PayParts\OrderCreationService;
use PaynlPayment\Shopware6\Service\PayParts\TransactionLinker;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'auth_required' => true])]
class PayPartsCheckoutController extends StorefrontController
{
    private OrderCreationService $orderCreationService;
    private TransactionLinker $transactionLinker;
    private LoggerInterface $logger;

    public function __construct(
        OrderCreationService $orderCreationService,
        TransactionLinker    $transactionLinker,
        LoggerInterface      $logger
    ) {
        $this->orderCreationService = $orderCreationService;
        $this->transactionLinker    = $transactionLinker;
        $this->logger               = $logger;
    }

    /**
     * Called from the frontend during `onSubmit`.
     * Creates the Shopware order from the current cart and returns the
     * identifiers the frontend needs to link the PAY.Parts transaction later.
     */
    #[Route(
        path: '/PaynlPayment/payparts/create-order',
        name: 'frontend.PaynlPayment.payparts.create-order',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function createOrder(SalesChannelContext $context): JsonResponse
    {
        try {
            $result = $this->orderCreationService->createFromContext($context);

            return new JsonResponse([
                'orderId'            => $result->getOrderId(),
                'orderTransactionId' => $result->getOrderTransactionId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('PAY.Parts order creation failed: ' . $e->getMessage(), ['exception' => $e]);

            return new JsonResponse(
                ['error' => 'Order creation failed'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Called from the frontend during `onSuccess`.
     * Stores the paynl_transactions record that links the PAY.Parts transaction
     * to the Shopware order so the exchange-URL notification can update state.
     */
    #[Route(
        path: '/PaynlPayment/payparts/link-transaction',
        name: 'frontend.PaynlPayment.payparts.link-transaction',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function linkTransaction(Request $request, SalesChannelContext $context): JsonResponse
    {
        $body               = json_decode($request->getContent(), true) ?? [];
        $paynlTransactionId = (string) ($body['paynlTransactionId'] ?? '');
        $orderTransactionId = (string) ($body['orderTransactionId'] ?? '');

        if ($paynlTransactionId === '' || $orderTransactionId === '') {
            return new JsonResponse(
                ['error' => 'Missing paynlTransactionId or orderTransactionId'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $redirectUrl = $this->transactionLinker->link(
                $paynlTransactionId,
                $orderTransactionId,
                $context->getContext()
            );

            return new JsonResponse(['redirectUrl' => $redirectUrl]);
        } catch (\Throwable $e) {
            $this->logger->error('PAY.Parts transaction link failed: ' . $e->getMessage(), ['exception' => $e]);

            return new JsonResponse(
                ['error' => 'Transaction link failed'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
