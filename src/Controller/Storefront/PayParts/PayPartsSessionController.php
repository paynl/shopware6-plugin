<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\PayParts;

use PaynlPayment\Shopware6\Exceptions\PayPartsApiException;
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
    private LoggerInterface $logger;

    public function __construct(
        SessionService $sessionService,
        CartService $cartService,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->sessionService  = $sessionService;
        $this->cartService     = $cartService;
        $this->orderRepository = $orderRepository;
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
     * @throws PayPartsApiException when the customer is not authenticated or the order is not found
     */
    private function loadOrderForCustomer(string $orderId, SalesChannelContext $context): OrderEntity
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new PayPartsApiException('Unauthenticated', Response::HTTP_FORBIDDEN);
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addFilter(new EqualsFilter('orderCustomer.customerId', $customer->getId()));
        $criteria->addAssociations(['lineItems', 'currency', 'billingAddress.country', 'deliveries']);

        $order = $this->orderRepository->search($criteria, $context->getContext())->first();

        if (!$order instanceof OrderEntity) {
            throw new PayPartsApiException('Order not found or access denied', Response::HTTP_FORBIDDEN);
        }

        return $order;
    }
}
