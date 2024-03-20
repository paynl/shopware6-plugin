<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Refund\Sw6;

use PaynlPayment\Shopware6\Controller\Api\Refund\RefundControllerBase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class RefundController extends RefundControllerBase
{
    /**
     * @Route(
     *     "/api/paynl/get-refund-data",
     *     name="api.PaynlPayment.getRefundDataSW64",
     *     methods={"GET"},
     *     defaults={"_routeScope"={"api"}}
     *     )
     */
    public function getRefundData(Request $request): JsonResponse
    {
        return $this->getRefundDataResponse($request);
    }

    /**
     * @Route(
     *     "/api/paynl/refund",
     *     name="frontend.PaynlPayment.refundSW64",
     *     methods={"POST"},
     *     defaults={"_routeScope"={"api"}}
     *     )
     */
    public function refund(Request $request): JsonResponse
    {
        return $this->getRefundResponse($request);
    }
}
