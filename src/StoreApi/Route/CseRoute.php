<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\StoreApi\Route;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Helper\PublicKeysHelper;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
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
    private EntityRepositoryInterface $orderRepository;
    private Api $api;
    private PublicKeysHelper $publicKeysHelper;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        Api $api,
        PublicKeysHelper $publicKeysHelper
    ) {
        $this->orderRepository = $orderRepository;
        $this->api = $api;
        $this->publicKeysHelper = $publicKeysHelper;
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
        $payload = json_decode($request->get('pay_encrypted_data'), true);
        $order = $this->getLastOrder($context);

        $auth = $this->api->startEncryptedTransaction($order, $payload, $context, '', '', '', '');

        return new JsonResponse($auth->getData());

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
        $transactionId = $request->get('transaction_id');

        $data = [];
        if (!empty($transactionId)) {
            $result = $this->api->status($transactionId, $context->getSalesChannel()->getId());
            $data = $result->getData();
        }

        return new JsonResponse($data);
    }

    /**
     * @Route("/PaynlPayment/cse/authentication",
     *     name="store-api.PaynlPayment.cse.authentication",
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
     *     name="store-api.PaynlPayment.cse.authorization",
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

    /**
     * @Route("/PaynlPayment/cse/publicKeys",
     *     name="store-api.PaynlPayment.cse.publicKeys",
     *     defaults={"csrf_protected"=false},
     *     methods={"GET"}
     *     )
     */
    public function refreshPublicKeys(Request $request, SalesChannelContext $context): Response
    {
        $keys = $this->publicKeysHelper->getKeys($context->getSalesChannel()->getId(), true);

        return new JsonResponse($keys);
    }

    private function getLastOrder(SalesChannelContext $context): ?OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('shippingAddress');   # important for subscription creation
        $criteria->addAssociation('billingAddress');    # important for subscription creation
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('orderCustomer.salutation');
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.product.media');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.positions.orderLineItem');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('transactions.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('transactions.stateMachineState');

        return $this->orderRepository->search($criteria, $context->getContext())->first();
    }
}
