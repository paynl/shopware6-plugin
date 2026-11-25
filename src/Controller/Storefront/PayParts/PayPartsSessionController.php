<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\PayParts;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PayPartsApiException;
use PaynlPayment\Shopware6\Exceptions\PayPartsLinkException;
use PaynlPayment\Shopware6\Exceptions\PayPartsOrderException;
use PaynlPayment\Shopware6\Service\CartService;
use PaynlPayment\Shopware6\Service\PayParts\EditOrderTransactionService;
use PaynlPayment\Shopware6\Service\PayParts\OrderCreationService;
use PaynlPayment\Shopware6\Service\PayParts\SessionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route(defaults: ['_routeScope' => ['storefront'], '_loginRequired' => true, '_loginRequiredAllowGuest' => true])]
class PayPartsSessionController extends StorefrontController
{
    private SessionService $sessionService;
    private OrderCreationService $orderCreationService;
    private CartService $cartService;
    private EditOrderTransactionService $editOrderTransactionService;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        SessionService $sessionService,
        OrderCreationService $orderCreationService,
        CartService $cartService,
        EditOrderTransactionService $editOrderTransactionService,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->sessionService = $sessionService;
        $this->orderCreationService = $orderCreationService;
        $this->cartService = $cartService;
        $this->editOrderTransactionService = $editOrderTransactionService;
        $this->config = $config;
        $this->logger = $logger;
    }

    #[Route(
        '/PaynlPayment/payparts/session',
        name: 'frontend.PaynlPayment.payparts.session',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function createSession(Request $request, SalesChannelContext $context): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? array_filter($body, 'is_scalar') : []; // only scalars: casting an array to string would error
        $orderId = trim((string)($body['orderId'] ?? ''));
        $paymentMethodId = trim((string)($body['paymentMethodId'] ?? '')) ?: null;
        $amountData = [];

        try {
            $salesChannelId = $context->getSalesChannel()->getId();

            if (!$this->config->isPayPartsCreditCardWidgetEnabled($salesChannelId)) {
                return new JsonResponse(
                    ['error' => 'Could not initialize payment session'],
                    Response::HTTP_FORBIDDEN
                );
            }

            if ($orderId !== '') {
                $order = $this->loadOrderForCustomer($orderId, $context);
                $response = $this->sessionService->createFromOrder($order, $context);
                $amountData = [
                    'amountCents' => (int) round($order->getAmountTotal() * 100),
                    'currency' => $order->getCurrency()?->getIsoCode() ?? $context->getCurrency()->getIsoCode(),
                ];
            } else {
                // Calculate the cart for the card method so surcharges match the order created later,
                // without changing the customer's selected method (the native submit button must keep working).
                $context = $this->orderCreationService->withCardMethod($context, $paymentMethodId);
                $cart = $this->cartService->getCalculatedMainCart($context);
                $response = $this->sessionService->createFromCart($cart, $context);
                $amountData = [
                    'amountCents' => (int) round($cart->getPrice()->getTotalPrice() * 100),
                    'currency' => $context->getCurrency()->getIsoCode(),
                ];
            }

            return new JsonResponse(array_merge([
                'sessionToken' => $response->getSessionToken(),
                'sessionId' => $response->getSessionId(),
                'apiUrl' => $this->sessionService->getApiUrl(),
            ], $amountData));
        } catch (PayPartsOrderException $exception) {
            $this->logger->warning('PAY.Parts session denied', ['reason' => $exception->getMessage()]);

            return new JsonResponse(
                ['error' => 'Could not initialize payment session'],
                $exception->getStatusCode()
            );
        } catch (PayPartsLinkException $exception) {
            $this->logger->warning('PAY.Parts session access denied', [
                'reason' => $exception->getMessage(),
                'orderId' => $orderId,
            ]);

            return new JsonResponse(
                ['error' => 'Could not initialize payment session'],
                $exception->getStatusCode()
            );
        } catch (PayPartsApiException $exception) {
            $this->logger->error('PAY.Parts session create failed', ['exception' => $exception]);

            return new JsonResponse(
                ['error' => 'Could not initialize payment session'],
                Response::HTTP_BAD_GATEWAY
            );
        } catch (Throwable $exception) {
            // e.g. an invalid order id (DAL) or a cart calculation error: answer JSON, never an HTML 500 page.
            $this->logger->error('PAY.Parts session failed', ['exception' => $exception, 'orderId' => $orderId]);

            return new JsonResponse(
                ['error' => 'Could not initialize payment session'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /** @throws PayPartsLinkException when the order is not the logged-in customer's */
    private function loadOrderForCustomer(string $orderId, SalesChannelContext $context): OrderEntity
    {
        return $this->editOrderTransactionService->loadCustomerOrder(
            $orderId,
            $context,
            (new Criteria())->addAssociations(['currency', 'billingAddress.country'])
        );
    }
}
