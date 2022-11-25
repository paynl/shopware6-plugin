<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\StoreApi\Route;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CseRoute
{
    /**
     * @Route("/PaynlPayment/cse/execute",
     *     name="store-api.PaynlPayment.cse.execute",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     *     )
     */
    public function execute(Request $request, SalesChannelContext $context): Response
    {
        // TODO temporary test response
        $arrEncryptedTransactionResult['result'] = 1;
        $arrEncryptedTransactionResult['nextAction'] = 'paid';
        $arrEncryptedTransactionResult['orderId'] = '1234567890X12345';
        $arrEncryptedTransactionResult['entranceCode'] = '12345';
        $arrEncryptedTransactionResult['transaction'] = ['transactionId' => '1234567890X12345', 'entranceCode' => '12345'];
        $arrEncryptedTransactionResult['entityId'] = '1';

        return new JsonResponse($arrEncryptedTransactionResult);
    }
}
