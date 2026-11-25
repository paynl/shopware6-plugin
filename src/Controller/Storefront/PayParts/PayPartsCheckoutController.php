<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\PayParts;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PayPartsLinkException;
use PaynlPayment\Shopware6\Exceptions\PayPartsOrderException;
use PaynlPayment\Shopware6\Service\PayParts\EditOrderTransactionService;
use PaynlPayment\Shopware6\Service\PayParts\OrderCreationService;
use PaynlPayment\Shopware6\Service\PayParts\TransactionLinker;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route(defaults: ['_routeScope' => ['storefront'], '_loginRequired' => true, '_loginRequiredAllowGuest' => true])]
class PayPartsCheckoutController extends StorefrontController
{
    private OrderCreationService $orderCreationService;
    private EditOrderTransactionService $editOrderTransactionService;
    private TransactionLinker $transactionLinker;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        OrderCreationService $orderCreationService,
        EditOrderTransactionService $editOrderTransactionService,
        TransactionLinker $transactionLinker,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->orderCreationService = $orderCreationService;
        $this->editOrderTransactionService = $editOrderTransactionService;
        $this->transactionLinker = $transactionLinker;
        $this->config = $config;
        $this->logger = $logger;
    }

    #[Route(
        path: '/PaynlPayment/payparts/create-order',
        name: 'frontend.PaynlPayment.payparts.create-order',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function createOrder(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$this->config->isPayPartsCreditCardWidgetEnabled($context->getSalesChannelId())) {
            return new JsonResponse(
                ['error' => 'Order creation failed'],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $body = [];
        }
        $body = is_array($body) ? array_filter($body, 'is_scalar') : []; // only scalars: casting an array to string would error
        $paymentMethodId = trim((string) ($body['paymentMethodId'] ?? '')) ?: null;
        $amountCents = isset($body['amountCents']) && is_numeric($body['amountCents'])
            ? (int) $body['amountCents']
            : null;
        $currency = trim((string) ($body['currency'] ?? '')) ?: null;
        $orderId = trim((string) ($body['orderId'] ?? ''));

        try {
            // Edit-order retry: no new order, make sure the existing order has an open card transaction.
            if ($orderId !== '') {
                $orderTransactionId = $this->editOrderTransactionService->ensureOpenCardTransaction(
                    $orderId,
                    $context,
                    $paymentMethodId ?? throw PayPartsOrderException::invalidPaymentMethod(),
                    $amountCents,
                    $currency
                );

                return new JsonResponse(['orderId' => $orderId, 'orderTransactionId' => $orderTransactionId]);
            }

            // Only the confirm-form fields Shopware's order validation and order creation read.
            $orderData = new DataBag(array_map('strval', array_intersect_key(
                $body,
                array_flip(['tos', 'revocation', 'customerComment', 'affiliateCode', 'campaignCode'])
            )));

            $result = $this->orderCreationService->createFromContext($context, $paymentMethodId, $amountCents, $currency, $orderData);

            return new JsonResponse([
                'orderId' => $result->getOrderId(),
                'orderTransactionId' => $result->getOrderTransactionId(),
                'editOrderUrl' => $result->getEditOrderUrl(),
            ]);
        } catch (PayPartsLinkException $exception) {
            $this->logger->warning('PAY.Parts order creation access denied', ['orderId' => $orderId]);

            return new JsonResponse(['error' => 'Order creation failed'], $exception->getStatusCode());
        } catch (PayPartsOrderException $exception) {
            $this->logger->warning('PAY.Parts order creation denied', [
                'reason' => $exception->getMessage(),
                'cause' => $exception->getPrevious()?->getMessage(), // Shopware's own reason, e.g. product sold out
                'statusCode' => $exception->getStatusCode(),
            ]);

            return new JsonResponse(
                ['error' => 'Order creation failed'],
                $exception->getStatusCode()
            );
        } catch (Throwable $exception) {
            $this->logger->error('PAY.Parts order creation failed', ['exception' => $exception]);

            return new JsonResponse(
                ['error' => 'Order creation failed'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route(
        path: '/PaynlPayment/payparts/link-transaction',
        name: 'frontend.PaynlPayment.payparts.link-transaction',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function linkTransaction(Request $request, SalesChannelContext $context): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? array_filter($body, 'is_scalar') : []; // only scalars: casting an array to string would error
        $paynlTransactionId = (string)($body['paynlTransactionId'] ?? '');
        $orderTransactionId = (string)($body['orderTransactionId'] ?? '');

        // PAY. order ids look like "47529675044X44d4"; anything else never reaches the lock or the PAY. API.
        if (!preg_match('/^[A-Za-z0-9]{1,64}$/', $paynlTransactionId) || $orderTransactionId === '') {
            return new JsonResponse(
                ['error' => 'Missing required parameters'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $redirectUrl = $this->transactionLinker->link(
                $paynlTransactionId,
                $orderTransactionId,
                $context
            );

            return new JsonResponse(['redirectUrl' => $redirectUrl]);
        } catch (PayPartsLinkException $exception) {
            $this->logger->warning('PAY.Parts transaction link denied', [
                'reason' => $exception->getMessage(),
                'statusCode' => $exception->getStatusCode(),
                'orderTransactionId' => $orderTransactionId,
            ]);

            return new JsonResponse(
                ['error' => 'Transaction link failed'],
                $exception->getStatusCode()
            );
        } catch (Throwable $exception) {
            $this->logger->error('PAY.Parts transaction link failed', [
                'exception' => $exception,
                'orderTransactionId' => $orderTransactionId,
            ]);

            return new JsonResponse(
                ['error' => 'Transaction link failed'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * The browser is the only place that knows about a payment without a matching order, or a partial payment.
     * This only writes a log entry so support can match the PAY.nl transaction to a customer.
     */
    #[Route(
        path: '/PaynlPayment/payparts/report-issue',
        name: 'frontend.PaynlPayment.payparts.report-issue',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function reportIssue(Request $request, SalesChannelContext $context): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? array_filter($body, 'is_scalar') : []; // only scalars: casting an array to string would error
        $type = in_array($body['type'] ?? '', ['payment-without-order', 'partial-payment', 'link-failed'], true)
            ? $body['type']
            : 'unknown';

        $this->logger->warning('PAY.Parts payment needs manual follow-up', [
            'type' => $type,
            'paynlTransactionId' => substr((string) ($body['paynlTransactionId'] ?? ''), 0, 64),
            'orderId' => substr((string) ($body['orderId'] ?? ''), 0, 64),
            'orderTransactionId' => substr((string) ($body['orderTransactionId'] ?? ''), 0, 64),
            'status' => is_numeric($body['status'] ?? null) ? (int) $body['status'] : null,
            'amountRemaining' => is_numeric($body['amountRemaining'] ?? null) ? (int) $body['amountRemaining'] : null,
            'customerId' => $context->getCustomer()?->getId(),
        ]);

        return new JsonResponse(['logged' => true]);
    }
}
