<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Config;

use Exception;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\InstallHelper;
use PaynlPayment\Shopware6\Helper\SettingsHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
class ConfigController extends AbstractController
{
    private const CREDENTIAL_MAX_LENGTH = 255;

    private Config $config;
    private Api $payApi;
    public InstallHelper $installHelper;
    private SettingsHelper $settingsHelper;

    public function __construct(
        Config $config,
        Api $payApi,
        InstallHelper $installHelper,
        SettingsHelper $settingsHelper
    ) {
        $this->installHelper = $installHelper;
        $this->payApi = $payApi;
        $this->config = $config;
        $this->settingsHelper = $settingsHelper;
    }

    #[Route('/api/paynl/install-payment-methods', name: 'api.action.PaynlPayment.installPaymentMethods', methods: ['POST'])]
    public function installPaymentMethods(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->request->get('salesChannelId');

        if (!empty($salesChannelId)) {
            $invalidChannelResponse = $this->createInvalidSalesChannelResponse($salesChannelId, $context);
            if ($invalidChannelResponse !== null) {
                return $invalidChannelResponse;
            }
        }

        $salesChannelsIds = empty($salesChannelId)
            ? $this->installHelper->getSalesChannels($context)->getIds()
            : [$salesChannelId];

        try {
            if ($this->isSinglePaymentMethod($salesChannelsIds)) {
                $this->installSinglePaymentMethodSalesChannels($context, $salesChannelsIds);
            } else {
                $this->installPaymentMethodsSalesChannels($context, $salesChannelsIds);
            }

            return $this->json([
                'success' => true,
                'message' => 'paynlValidation.messages.paymentMethodsSuccessfullyInstalled',
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'paynlValidation.error.paymentMethodsInstallFailed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/paynl/get-payment-terminals', name: 'api.action.PaynlPayment.getPaymentTerminals', methods: ['GET'])]
    public function getPaymentTerminals(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');

        if (!empty($salesChannelId)) {
            $invalidChannelResponse = $this->createInvalidSalesChannelResponse($salesChannelId, $context);
            if ($invalidChannelResponse !== null) {
                return $invalidChannelResponse;
            }
        }

        $terminals = $this->settingsHelper->getTerminalsOptions($salesChannelId);

        return $this->json(['success' => true, 'data' => $terminals]);
    }

    #[Route('/api/paynl/test-api-keys', name: 'api.action.PaynlPayment.testApiKeys', methods: ['POST'])]
    public function testApiKeys(Request $request): JsonResponse
    {
        $tokenCode = $request->get('tokenCode');
        $apiToken = $request->get('apiToken');
        $serviceId = $request->get('serviceId');

        if (
            !is_string($tokenCode) || !is_string($apiToken) || !is_string($serviceId)
            || trim($tokenCode) === '' || trim($apiToken) === '' || trim($serviceId) === ''
            || strlen(trim($tokenCode)) > self::CREDENTIAL_MAX_LENGTH
            || strlen(trim($apiToken)) > self::CREDENTIAL_MAX_LENGTH
            || strlen(trim($serviceId)) > self::CREDENTIAL_MAX_LENGTH
        ) {
            return $this->json([
                'success' => false,
                'message' => 'paynlValidation.error.invalidCredentials',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($this->payApi->isValidCredentials(trim($tokenCode), trim($apiToken), trim($serviceId))) {
            return $this->json([
                'success' => true,
                'message' => 'paynlValidation.messages.correctCredentials',
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'paynlValidation.messages.wrongCredentials',
        ]);
    }

    /**
     * @param mixed $salesChannelId
     */
    private function createInvalidSalesChannelResponse($salesChannelId, Context $context): ?JsonResponse
    {
        if (!is_string($salesChannelId) || !Uuid::isValid($salesChannelId)) {
            return $this->json([
                'success' => false,
                'message' => 'paynlValidation.error.invalidSalesChannel',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($salesChannelId, $this->installHelper->getSalesChannels($context)->getIds(), true)) {
            return $this->json([
                'success' => false,
                'message' => 'paynlValidation.error.invalidSalesChannel',
            ], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    private function installPaymentMethodsSalesChannels(Context $context, array $salesChannels)
    {
        foreach ($salesChannels as $salesChannelId) {
            $this->installHelper->installPaymentMethods($salesChannelId, $context);
            $this->installHelper->activatePaymentMethods($context);
        }
    }

    private function installSinglePaymentMethodSalesChannels(Context $context, array $salesChannels)
    {
        $this->installHelper->deactivatePaymentMethods($context);

        foreach ($salesChannels as $salesChannelId) {
            $this->installHelper->addSinglePaymentMethod($salesChannelId, $context);

            $paymentMethodId = md5((string) InstallHelper::SINGLE_PAYMENT_METHOD_ID);
            $this->installHelper->setDefaultPaymentMethod($salesChannelId, $context, $paymentMethodId);
        }
    }

    private function isSinglePaymentMethod(array $salesChannelsIds): bool
    {
        foreach ($salesChannelsIds as $salesChannelsId) {
            if (!$this->config->getSinglePaymentMethodInd($salesChannelsId)) {
                return false;
            }
        }

        return true;
    }
}