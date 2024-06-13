<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\PayPal\Sw6;

use PaynlPayment\Shopware6\Controller\Storefront\PayPal\PayPalExpressControllerBase;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;

/**
 * @RouteScope(scopes={"storefront"})
 */
class PayPalExpressController extends PayPalExpressControllerBase
{

    /**
     * @Route(
     *     "/PaynlPayment/paypal-express/prepare-cart",
     *     name="frontend.account.PaynlPayment.paypal-express.prepare-cart",
     *     methods={"POST"},
     *     defaults={"XmlHttpRequest"=true, "csrf_protected"=false}
     * )
     */
    public function expressPrepareCart(Request $request, SalesChannelContext $context): Response
    {
        return $this->getExpressPrepareCartResponse($request, $context);
    }

    /**
     * @Route(
     *     "/PaynlPayment/paypal-express/start-payment",
     *     name="frontend.account.PaynlPayment.paypal-express.start-payment",
     *     methods={"POST"},
     *     defaults={"XmlHttpRequest"=true, "csrf_protected"=false}
     *     )
     */
    public function startPayment(SalesChannelContext $context, Request $request): Response
    {
        return $this->getStartPaymentResponse($context, $request);
    }

    /**
     * @Route(
     *     "/PaynlPayment/paypal-express/finish-payment",
     *     name="frontend.account.PaynlPayment.paypal-express.finish-payment",
     *     methods={"GET", "POST"},
     *     defaults={"csrf_protected"=false, "_routeScope"={"storefront"}},
     *     )
     */
    public function finishPayment(RequestDataBag $data, SalesChannelContext $context): Response
    {
        return $this->getFinishPaymentResponse($data, $context);
    }

    /**
     * @Route(
     *     "/PaynlPayment/paypal-express/finish-page",
     *     name="frontend.account.PaynlPayment.paypal-express.finish-page",
     *     methods={"GET", "POST"},
     *     defaults={"csrf_protected"=false, "_routeScope"={"storefront"}},
     *     )
     */
    public function finishPage(Request $request, SalesChannelContext $context): Response
    {
        return $this->getFinishPageResponse($request, $context);
    }
}
