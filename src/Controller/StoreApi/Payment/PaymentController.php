<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\StoreApi\Payment;

use PaynlPayment\Shopware6\Service\Payment\PaymentFinalizeFacade;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/store-api/paynl/payment', defaults: ['_routeScope' => ['store-api'], 'auth_required' => false, 'auth_enabled' => false])]
class PaymentController extends AbstractController
{
    private PaymentFinalizeFacade $finalizeFacade;

    public function __construct(PaymentFinalizeFacade $finalizeFacade)
    {
        $this->finalizeFacade = $finalizeFacade;
    }

    #[Route('/finalize-transaction', name: 'store-api.PaynlPayment.finalize-transaction', methods: ['GET'])]
    public function finalizeTransaction(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        return $this->finalizeFacade->finalizeTransaction($request, $salesChannelContext->getContext())->getResponse();
    }
}
