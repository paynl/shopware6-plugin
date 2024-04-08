<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Refund\Sw65;

use PaynlPayment\Shopware6\Controller\Api\Refund\RefundControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
class RefundController extends RefundControllerBase
{
    #[Route('/api/paynl/get-refund-data', name: 'api.PaynlPayment.getRefundData', methods: ['GET'])]
    public function getRefundData(Request $request): JsonResponse
    {
        return $this->getRefundDataResponse($request);
    }

    #[Route('/api/paynl/refund', name: 'frontend.PaynlPayment.refund', methods: ['POST'])]
    public function refund(Request $request): JsonResponse
    {
        return $this->getRefundResponse($request);
    }
}
