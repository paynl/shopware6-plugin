<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\CheckoutHistoryBack\Sw6;

use PaynlPayment\Shopware6\Controller\Storefront\CheckoutHistoryBack\CheckoutHistoryBackControllerBase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CheckoutHistoryBackController extends CheckoutHistoryBackControllerBase
{
    /**
     * @Route(
     *     "/checkout/history/back",
     *     name="frontend.checkout.Paynl.history.back",
     *     options={"seo"="false"},
     *     methods={"GET"},
     *     defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true, "_noStore"=true, "_routeScope"={"storefront"}}
     * )
     */
    public function historyBackProxy(): Response
    {
        return $this->getHistoryBackProxy();
    }
}
