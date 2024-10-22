<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Ideal\Sw65;

use PaynlPayment\Shopware6\Controller\Storefront\Ideal\IdealExpressControllerBase;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class IdealExpressController extends IdealExpressControllerBase
{
    #[Route('/PaynlPayment/ideal-express/start-payment', name: 'frontend.account.PaynlPayment.ideal-express.start-payment', options: ['seo' => false], methods: ['GET'])]
    public function startPayment(SalesChannelContext $context, Request $request): Response
    {
        return $this->getStartPaymentResponse($context, $request);
    }

    #[Route(path: '/PaynlPayment/ideal-express/finish-payment', name: 'frontend.account.PaynlPayment.ideal-express.finish-payment', options: ['seo' => false], methods: ['POST', 'GET'])]
    public function finishPayment(RequestDataBag $data, SalesChannelContext $context): Response
    {
        return $this->getFinishPaymentResponse($data, $context);
    }

    #[Route(path: '/PaynlPayment/ideal-express/finish-page', name: 'frontend.account.PaynlPayment.ideal-express.finish-page', options: ['seo' => false], methods: ['POST', 'GET'])]
    public function finishPage(Request $request, SalesChannelContext $context): Response
    {
        return $this->getFinishPageResponse($request, $context);
    }
}
