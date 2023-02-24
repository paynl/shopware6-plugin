<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\StoreApi\Route;

use Exception;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Helper\PluginHelper;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Helper\PublicKeysHelper;
use PaynlPayment\Shopware6\Service\Order\OrderService;
use PaynlPayment\Shopware6\Service\Paynl\TransactionStateService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CseRoute
{
    private $router;
    private $api;
    private $orderService;
    private $transactionStateService;
    private $publicKeysHelper;
    private $pluginHelper;
    private $processingHelper;
    private $shopwareVersion;

    public function __construct(
        RouterInterface $router,
        Api $api,
        OrderService $orderService,
        TransactionStateService $transactionStateService,
        PublicKeysHelper $publicKeysHelper,
        PluginHelper $pluginHelper,
        ProcessingHelper $processingHelper,
        string $shopwareVersion
    ) {
        $this->router = $router;
        $this->api = $api;
        $this->orderService = $orderService;
        $this->transactionStateService = $transactionStateService;
        $this->publicKeysHelper = $publicKeysHelper;
        $this->pluginHelper = $pluginHelper;
        $this->processingHelper = $processingHelper;
        $this->shopwareVersion = $shopwareVersion;
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
        try {
            $payload = json_decode($request->get('pay_encrypted_data'), true);
            $orderId = $request->get('orderId');
            $order = null;
            if ($orderId) {
                $order = $this->orderService->getOrder($orderId, $context->getContext());
            }

            if (empty($order)) {
                $order = $this->orderService->getLastOrder($context->getContext());
            }

            $exchangeUrl = $this->router->generate(
                'frontend.PaynlPayment.notify',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $auth = $this->api->startEncryptedTransaction(
                $order,
                $payload,
                $context,
                '',
                $exchangeUrl,
                $this->shopwareVersion,
                $this->pluginHelper->getPluginVersionFromComposer()
            );
            $arrEncryptedTransactionResult = $auth->getData();

            return new JsonResponse($arrEncryptedTransactionResult);
        } catch (Exception $exception) {
            $arrEncryptedTransactionResult = [
                'type' => 'error',
                'errorMessage' => $exception->getMessage(),
                'trace' => ''
            ];

            return new JsonResponse($arrEncryptedTransactionResult);
        }
    }

    /**
     * @Route("/PaynlPayment/cse/status",
     *     name="store-api.PaynlPayment.cse.status",
     *     defaults={"csrf_protected"=false},
     *     methods={"GET"}
     *     )
     */
    public function status(Request $request, SalesChannelContext $context): Response
    {
        $transactionId = $request->get('transactionId');

        try {
            if (!empty($transactionId)) {
                $result = $this->api->getAuthenticationStatus($transactionId, $context->getSalesChannel()->getId());
                $data = $result->getData();
            }
        } catch (Exception $exception) {
            $data = [];
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

        try {
            $data = $this->api->authenticaticate($params, $context->getSalesChannel()->getId())->getData();
        } catch (Exception $exception) {
            $data = [
                'success' => false,
                'errorMessage' => $exception->getMessage()
            ];
        }

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

        try {
            $data = $this->api->authorize($params, $context->getSalesChannel()->getId())->getData();
        } catch (Exception $exception) {
            $data = [
                'success' => false,
                'errorMessage' => $exception->getMessage()
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * @Route("/PaynlPayment/cse/cancel",
     *     name="store-api.PaynlPayment.cse.cancel",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     *     )
     */
    public function cancel(Request $request): Response
    {
        $transactionId = $request->get('transactionId');
        if (empty($transactionId)) {
            return new JsonResponse(['success' => false]);
        }

        try {
            $this->transactionStateService->cancel($transactionId);
        } catch (Exception $exception) {
            return new JsonResponse([
                'success' => false,
                'errorMessage' => $exception->getMessage()
            ]);
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/PaynlPayment/cse/fail",
     *     name="store-api.PaynlPayment.cse.fail",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     *     )
     */
    public function fail(Request $request): Response
    {
        $transactionId = $request->get('transactionId');
        if (empty($transactionId)) {
            return new JsonResponse(['success' => false]);
        }

        try {
            $this->transactionStateService->fail($transactionId);
        } catch (Exception $exception) {
            return new JsonResponse([
                'success' => false,
                'errorMessage' => $exception->getMessage()
            ]);
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/PaynlPayment/cse/updatePaymentStatusFromPay",
     *     name="store-api.PaynlPayment.cse.updatePaymentStatusFromPay",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     *     )
     */
    public function updatePaymentStatusFromPay(Request $request): Response
    {
        $transactionId = $request->get('transactionId');
        if (empty($transactionId)) {
            return new JsonResponse(['success' => false]);
        }

        try {
            $this->processingHelper->updatePaymentStatusFromPay($transactionId);
        } catch (Exception $exception) {
            return new JsonResponse([
                'success' => false,
                'errorMessage' => $exception->getMessage()
            ]);
        }

        return new JsonResponse(['success' => true]);
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
        try {
            $keys = $this->publicKeysHelper->getKeys($context->getSalesChannel()->getId(), true);
        } catch (Exception $exception) {
            return new JsonResponse([
                'success' => false,
                'errorMessage' => $exception->getMessage()
            ]);
        }

        return new JsonResponse($keys);
    }
}
