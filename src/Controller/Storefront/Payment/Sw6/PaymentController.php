<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Payment\Sw6;

use PaynlPayment\Shopware6\Controller\Storefront\Payment\PaymentControllerBase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;

/**
 * @RouteScope(scopes={"storefront"})
 */
class PaymentController extends PaymentControllerBase
{
    /**
     * @Route(
     *     "/PaynlPayment/finalize-transaction",
     *     name="frontend.PaynlPayment.finalize-transaction",
     *     options={"seo"="false"},
     *     methods={"GET"},
     *     defaults={"csrf_protected"=false, "_routeScope"={"storefront"}}
     * )
     */
    public function finalizeTransaction(Request $request, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        return $this->finalizeTransactionResponse($request, $salesChannelContext);
    }
}
