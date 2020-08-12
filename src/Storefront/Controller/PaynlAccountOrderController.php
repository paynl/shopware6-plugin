<?php

namespace PaynlPayment\Shopware6\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class PaynlAccountOrderController extends StorefrontController
{
    /** @var Session */
    private $session;

    public function __construct(Session $session) {
        $this->session = $session;
    }

    /**
     * @Route(
     *     "/PaynlPayment/order/change/payment",
     *     name="frontend.PaynlPayment.edit-order.change-payment-method",
     *     methods={"POST"},
     *     defaults={"csrf_protected"=false}
     *     )
     * @param Request $request
     * @return JsonResponse
     */
    public function orderChangePayment(Request $request): JsonResponse
    {
        $this->session->set('paynlIssuer', $request->get('paynlIssuer') ?: null);

        return new JsonResponse(['success' => true]);
    }
}
