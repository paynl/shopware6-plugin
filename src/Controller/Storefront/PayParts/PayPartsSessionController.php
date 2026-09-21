<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\PayParts;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PayPartsApiException;
use PaynlPayment\Shopware6\Exceptions\PayPartsLinkException;
use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use PaynlPayment\Shopware6\Service\CartService;
use PaynlPayment\Shopware6\Service\PayParts\SessionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'auth_required' => true])]
class PayPartsSessionController extends StorefrontController
{
    private SessionService $sessionService;
    private CartService $cartService;
    private OrderRepositoryInterface $orderRepository;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        SessionService $sessionService,
        CartService $cartService,
        OrderRepositoryInterface $orderRepository,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->sessionService  = $sessionService;
        $this->cartService     = $cartService;
        $this->orderRepository = $orderRepository;
        $this->config          = $config;
        $this->logger          = $logger;
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
        $body    = json_decode($request->getContent(), true) ?? [];
        $orderId = trim((string)($body['orderId'] ?? ''));

        try {
            $salesChannelId = $context->getSalesChannel()->getId();

            if (!$this->config->isPayPartsCreditCardWidgetEnabled($salesChannelId)) {
                return new JsonResponse(
                    ['error' => 'Could not initialize payment session'],
                    Response::HTTP_FORBIDDEN
                );
            }

            if ($orderId !== '') {
                $order    = $this->loadOrderForCustomer($orderId, $context);
                $response = $this->sessionService->createFromOrder($order, $context);
            } else {
                $cart     = $this->cartService->getCalculatedMainCart($context);
                $response = $this->sessionService->createFromCart($cart, $context);
            }

            return new JsonResponse([
                'sessionToken' => $response->getSessionToken(),
                'sessionId'    => $response->getSessionId(),
                'apiUrl'       => $this->sessionService->getApiUrl($salesChannelId),
            ]);
        } catch (PayPartsLinkException $exception) {
            $this->logger->warning('PAY.Parts session access denied', [
                'reason'  => $exception->getMessage(),
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
        }
    }

    /**
     * Loads an order by ID, verifying it belongs to the currently authenticated customer.
     *
     * @throws PayPartsLinkException when the customer is not authenticated or the order is not found
     */
    private function loadOrderForCustomer(string $orderId, SalesChannelContext $context): OrderEntity
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw PayPartsLinkException::accessDenied();
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addFilter(new EqualsFilter('orderCustomer.customerId', $customer->getId()));
        $criteria->addAssociations(['lineItems', 'currency', 'billingAddress.country', 'deliveries']);

        $order = $this->orderRepository->search($criteria, $context->getContext())->first();

        if (!$order instanceof OrderEntity) {
            throw PayPartsLinkException::accessDenied();
        }

        return $order;
    }
}
