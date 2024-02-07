<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\AccountOrder\Sw65;

use PaynlPayment\Shopware6\Controller\Storefront\AccountOrder\AccountOrderControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'auth_required' => true, 'auth_enabled' => true])]
class AccountOrderController extends AccountOrderControllerBase
{

    #[Route(
        path: '/PaynlPayment/order/change/paylater-fields',
        name: 'frontend.PaynlPayment.edit-order.change-paylater-fields',
        defaults: ['csrf_protected' => false, '_routeScope' => ['storefront']],
        methods: ['POST']
    )]
    public function orderChangePayLaterFields(Request $request): JsonResponse
    {
        return $this->getOrderChangePayLaterFieldResponse($request);
    }
}
