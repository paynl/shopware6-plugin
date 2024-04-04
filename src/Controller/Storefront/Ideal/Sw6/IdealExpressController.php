<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Ideal\Sw6;

use PaynlPayment\Shopware6\Controller\Storefront\Ideal\IdealExpressControllerBase;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;

/**
 * @RouteScope(scopes={"storefront"})
 */
class IdealExpressController extends IdealExpressControllerBase
{

    /**
     * @Route(
     *     "/PaynlPayment/paypal/start-payment",
     *     name="frontend.account.PaynlPayment.ideal-express.start-payment",
     *     methods={"GET"},
     *     defaults={"csrf_protected"=false, "_routeScope"={"storefront"}},
     *     )
     */
    public function startPayment(SalesChannelContext $context, Request $request): Response
    {
        return $this->getStartPaymentResponse($context, $request);
    }

    /**
     * @Route(
     *     "/PaynlPayment/ideal-express/finish-payment",
     *     name="frontend.account.PaynlPayment.ideal-express.finish-payment",
     *     methods={"GET", "POST"},
     *     defaults={"csrf_protected"=false, "_routeScope"={"storefront"}},
     *     )
     */
    public function finishPayment(RequestDataBag $data, SalesChannelContext $context): Response
    {
        return $this->getFinishPaymentResponse($data, $context);
    }
}
