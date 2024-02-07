<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Config\Sw65;

use PaynlPayment\Shopware6\Controller\Api\Config\ConfigControllerBase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
class ConfigController extends ConfigControllerBase
{
    #[Route(
        path: '/api/paynl/install-payment-methods',
        name: 'api.action.PaynlPayment.installPaymentMethods',
        methods: ['GET']
    )]
    public function installPaymentMethods(Request $request, Context $context): JsonResponse
    {
        return $this->getInstallPaymentMethodsResponse($request, $context);
    }

    #[Route(
        path: '/api/paynl/store-settings',
        name: 'api.action.PaynlPayment.storeSettings',
        methods: ['POST']
    )]
    public function storeSettings(Request $request, Context $context): JsonResponse
    {
        return $this->getStoreSettingsResponse($request, $context);
    }

    #[Route(
        path: '/api/paynl/get-payment-terminals',
        name: 'api.action.PaynlPayment.getPaymentTerminals',
        methods: ['GET']
    )]
    public function getPaymentTerminals(Request $request): JsonResponse
    {
        return $this->getPaymentTerminalsResponse($request);
    }

    #[Route(
        path: '/api/paynl/test-api-keys',
        name: 'api.action.PaynlPayment.testApiKeys',
        methods: ['POST']
    )]
    public function testApiKeys(Request $request): JsonResponse
    {
        return $this->getTestApiKeysResponse($request);
    }
}
