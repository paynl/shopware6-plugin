<?php declare(strict_types=1);

namespace PaynlPayment\Controller;

use PaynlPayment\Components\Api;
use PaynlPayment\Components\Config;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Paynl\Error;

/**
 * @RouteScope(scopes={"api"})
 */
class StatusTransitionController extends AbstractController
{
    private $paynlApi;
    private $paynlConfig;

    public function __construct(
        Api $paynlApi,
        Config $paynlConfig
    ) {
        $this->paynlApi = $paynlApi;
        $this->paynlConfig = $paynlConfig;
    }

    /**
     * @Route("/api/v1/paynl/change-transaction-status", name="api.PaynlPayment.changeTransactionStatus", methods={"POST"})
     */
    public function changeTransactionStatus(Request $request): JsonResponse
    {
//        $paynlTransactionId = $request->get('transactionId');
        try {
//            $apiTransaction = $this->paynlApi->getTransaction($paynlTransactionId);
//            $refundedAmount = $apiTransaction->getRefundedAmount();
//            $availableForRefund = $apiTransaction->getAmount() - $refundedAmount;

            return new JsonResponse($request->request->all());
        } catch (Error\Api $exception) {
            return new JsonResponse([
                'errorMessage' => $exception->getMessage()
            ], 400);
        }
    }
}
