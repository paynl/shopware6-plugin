<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Payment;

use PaynlPayment\Shopware6\Service\Payment\PaymentFinalizeFacade;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false, 'auth_required' => false, 'auth_enabled' => false])]
class PaymentController extends AbstractController
{
    private PaymentFinalizeFacade $finalizeFacade;

    public function __construct(PaymentFinalizeFacade $finalizeFacade)
    {
        $this->finalizeFacade = $finalizeFacade;
    }

    #[Route('/PaynlPayment/finalize-transaction', name: 'frontend.PaynlPayment.finalize-transaction', options: ['seo' => false], methods: ['GET'])]
    public function finalizeTransaction(Request $request, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        return $this->finalizeFacade->finalizeTransaction($request, $salesChannelContext->getContext())->getResponse();
    }
}
