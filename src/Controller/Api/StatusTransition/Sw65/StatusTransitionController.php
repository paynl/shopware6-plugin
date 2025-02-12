<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\StatusTransition\Sw65;

use PaynlPayment\Shopware6\Controller\Api\StatusTransition\StatusTransitionControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
class StatusTransitionController extends StatusTransitionControllerBase
{
    #[Route('/api/paynl/change-transaction-status', name: 'api.PaynlPayment.changeTransactionStatus', methods: ['POST'])]
    public function changeTransactionStatus(Request $request): JsonResponse
    {
        return $this->getChangeTransactionStatusResponse($request);
    }
}
