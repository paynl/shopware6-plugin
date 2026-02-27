<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Payment;

use PaynlPayment\Shopware6\Service\Payment\PaymentFinalizeFacade;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/paynl/payment', defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
class PaymentController extends AbstractController
{
    private PaymentFinalizeFacade $finalizeFacade;

    public function __construct(PaymentFinalizeFacade $finalizeFacade)
    {
        $this->finalizeFacade = $finalizeFacade;
    }

    #[Route('/finalize-transaction', name: 'api.PaynlPayment.finalize-transaction', methods: ['GET'])]
    public function finalizeTransaction(Request $request, Context $context): Response
    {
        $result = $this->finalizeFacade->finalizeTransaction($request, $context);

        return $result->getResponse();
    }
}
