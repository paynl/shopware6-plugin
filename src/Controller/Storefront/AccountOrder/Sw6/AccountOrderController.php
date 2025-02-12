<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\AccountOrder\Sw6;

use PaynlPayment\Shopware6\Controller\Storefront\AccountOrder\AccountOrderControllerBase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class AccountOrderController extends AccountOrderControllerBase
{
    /**
     * @Route(
     *     "/PaynlPayment/order/change/paylater-fields",
     *     name="frontend.PaynlPayment.edit-order.change-paylater-fields",
     *     methods={"POST"},
     *     defaults={"csrf_protected"=false, "_routeScope"={"storefront"}},
     *     )
     */
    public function orderChangePayLaterFields(Request $request): JsonResponse
    {
        return $this->getOrderChangePayLaterFieldResponse($request);
    }
}
