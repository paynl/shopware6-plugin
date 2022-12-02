<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\StoreApi\Route;

use PaynlPayment\Shopware6\Components\Api;
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
    private Api $api;

    public function __construct(Api $api)
    {
        $this->api = $api;
    }

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

    /**
     * @Route("/PaynlPayment/cse/status",
     *     name="store-api.PaynlPayment.cse.status",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     *     )
     */
    public function status(Request $request, SalesChannelContext $context): Response
    {
        $transactionId = $request->get('tranasction_id');

        $data = [];
        if (!empty($transactionId)) {
            $result = $this->api->status($transactionId, $context->getSalesChannel()->getId());
            $data = $result->getData();
        }

        return new JsonResponse($data);
    }

    /**
     * @Route("/PaynlPayment/cse/authentication",
     *     name="store-api.PaynlPayment.cse.status",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     *     )
     */
    public function authentication(Request $request, SalesChannelContext $context): Response
    {
        $params = $request->request->all();

        $data = $this->api->authentication($params, $context->getSalesChannel()->getId())->getData();

        return new JsonResponse($data);
    }

    /**
     * @Route("/PaynlPayment/cse/authorization",
     *     name="store-api.PaynlPayment.cse.status",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     *     )
     */
    public function authorization(Request $request, SalesChannelContext $context): Response
    {
        $params = $request->request->all();

        $data = $this->api->authorization($params, $context->getSalesChannel()->getId())->getData();

        return new JsonResponse($data);
    }
}
