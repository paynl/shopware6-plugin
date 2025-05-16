<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\AccountOrder\Sw65;

use PaynlPayment\Shopware6\Controller\Storefront\AccountOrder\AccountOrderControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Error;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class AccountOrderController extends AccountOrderControllerBase
{

    #[Route(
        '/PaynlPayment/order/change/paylater-fields',
        name: 'frontend.PaynlPayment.edit-order.change-paylater-fields',
        defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false],
        methods: ['POST']
    )]
    public function orderChangePayLaterFields(Request $request): JsonResponse
    {
        return $this->getOrderChangePayLaterFieldResponse($request);
    }
}
