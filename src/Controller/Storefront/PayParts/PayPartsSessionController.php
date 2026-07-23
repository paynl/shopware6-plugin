<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\PayParts;

use PaynlPayment\Shopware6\Exceptions\PayPartsApiException;
use PaynlPayment\Shopware6\Service\CartService;
use PaynlPayment\Shopware6\Service\PayParts\SessionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'auth_required' => false])]
class PayPartsSessionController extends StorefrontController
{
    private SessionService $sessionService;
    private CartService $cartService;
    private LoggerInterface $logger;

    public function __construct(
        SessionService $sessionService,
        CartService $cartService,
        LoggerInterface $logger
    ) {
        $this->sessionService = $sessionService;
        $this->cartService = $cartService;
        $this->logger = $logger;
    }

    #[Route(
        '/PaynlPayment/payparts/session',
        name: 'frontend.PaynlPayment.payparts.session',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function createSession(SalesChannelContext $context): JsonResponse
    {
        try {
            $salesChannelId = $context->getSalesChannel()->getId();
            $cart           = $this->cartService->getCalculatedMainCart($context);
            $response       = $this->sessionService->createFromCart($cart, $context);

            return new JsonResponse([
                'sessionToken' => $response->getSessionToken(),
                'sessionId'    => $response->getSessionId(),
                'apiUrl'       => $this->sessionService->getApiUrl($salesChannelId),
            ]);
        } catch (PayPartsApiException $e) {
            $this->logger->error('PAY.Parts session create failed: ' . $e->getMessage(), ['exception' => $e]);

            return new JsonResponse(
                ['error' => 'Could not initialize payment session'],
                Response::HTTP_BAD_GATEWAY
            );
        }
    }
}