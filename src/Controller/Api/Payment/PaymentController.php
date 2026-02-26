<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Payment;

use PaynlPayment\Shopware6\Service\Payment\PaymentFinalizeFacade;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends AbstractController
{
    private PaymentFinalizeFacade $finalizeFacade;

    public function __construct(PaymentFinalizeFacade $finalizeFacade)
    {
        $this->finalizeFacade = $finalizeFacade;
    }

    public function finalizeTransaction(Request $request, Context $context): Response
    {
        $result = $this->finalizeFacade->finalizeTransaction($request, $context);

        return $result->getResponse();
    }
}
