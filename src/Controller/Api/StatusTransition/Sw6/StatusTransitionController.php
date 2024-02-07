<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\StatusTransition\Sw6;

use PaynlPayment\Shopware6\Controller\Api\StatusTransition\StatusTransitionControllerBase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class StatusTransitionController extends StatusTransitionControllerBase
{
    /**
     * @Route("/api/paynl/change-transaction-status",
     *     name="api.PaynlPayment.changeTransactionStatusSW64",
     *     methods={"POST"},
     *     defaults={"_routeScope"={"api"}}
     *     )
     */
    public function changeTransactionStatus(Request $request): JsonResponse
    {
        return $this->getChangeTransactionStatusResponse($request);
    }
}
