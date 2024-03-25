<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Config\Sw6;

use PaynlPayment\Shopware6\Controller\Api\Config\ConfigControllerBase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(defaults={"auth_required"=true, "auth_enabled"=true})
 */
class ConfigController extends ConfigControllerBase
{
    /**
     * @Route(
     *     "/api/paynl/install-payment-methods",
     *     name="api.action.PaynlPayment.installPaymentMethodsSW64",
     *     methods={"GET"}
     *     )
     */
    public function installPaymentMethods(Request $request, Context $context): JsonResponse
    {
        return $this->getInstallPaymentMethodsResponse($request, $context);
    }

    /**
     * @Route(
     *     "/api/paynl/store-settings",
     *     name="api.action.PaynlPayment.storeSettingsSW64",
     *     methods={"POST"}
     *     )
     */
    public function storeSettings(Request $request, Context $context): JsonResponse
    {
        return $this->getStoreSettingsResponse($request, $context);
    }

    /**
     * @Route(
     *     "/api/paynl/get-payment-terminals",
     *     name="api.action.PaynlPayment.get.payment-terminals",
     *     methods={"GET"}
     *     )
     */
    public function getPaymentTerminals(Request $request): JsonResponse
    {
        return $this->getPaymentTerminalsResponse($request);
    }

    /**
     * @Route(
     *     "/api/paynl/test-api-keys",
     *     name="api.action.PaynlPayment.test.api.keys",
     *     methods={"POST"}
     *     )
     */
    public function testApiKeys(Request $request): JsonResponse
    {
        return $this->getTestApiKeysResponse($request);
    }
}
