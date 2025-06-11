<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Config;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\InstallHelper;
use PaynlPayment\Shopware6\Helper\SettingsHelper;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
class ConfigController extends AbstractController
{
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

    #[Route('/api/paynl/install-payment-methods', name: 'api.action.PaynlPayment.installPaymentMethods', methods: ['GET'])]
    public function installPaymentMethods(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $salesChannelsIds = empty($salesChannelId) ? $this->installHelper->getSalesChannels($context)->getIds()
            : [$salesChannelId];

        if ($this->isSinglePaymentMethod($salesChannelsIds)) {
            $this->installSinglePaymentMethodSalesChannels($context, $salesChannelsIds);

            return $this->json([
                'success' => true,
                'message' => "paynlValidation.messages.paymentMethodsSuccessfullyInstalled"
            ]);
        }

        try {
            $this->installPaymentMethodsSalesChannels($context, $salesChannelsIds);

            return $this->json([
                'success' => true,
                'message' => "paynlValidation.messages.paymentMethodsSuccessfullyInstalled"
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    #[Route('/api/paynl/get-payment-terminals', name: 'api.action.PaynlPayment.getPaymentTerminals', methods: ['GET'])]
    public function getPaymentTerminals(Request $request): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');

        $terminals = $this->settingsHelper->getTerminalsOptions($salesChannelId);

        return $this->json(['success' => true, 'data' => $terminals]);
    }

    #[Route('/api/paynl/test-api-keys', name: 'api.action.PaynlPayment.testApiKeys', methods: ['POST'])]
    public function testApiKeys(Request $request): JsonResponse
    {
        $tokenCode = $request->get('tokenCode');
        $apiToken = $request->get('apiToken');
        $serviceId = $request->get('serviceId');

        if ($this->payApi->isValidCredentials($tokenCode, $apiToken, $serviceId)) {
            return $this->json([
                'success' => true,
                'message' => "paynlValidation.messages.correctCredentials"
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => "paynlValidation.messages.wrongCredentials"
        ]);
    }

    private function installPaymentMethodsSalesChannels(Context $context, array $salesChannels)
    {
        foreach ($salesChannels as $salesChannelId) {
            $this->installHelper->removeSinglePaymentMethod($salesChannelId, $context);
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
