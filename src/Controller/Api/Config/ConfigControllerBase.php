<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Config;

use Exception;
use Paynl\Config as SDKConfig;
use Paynl\Paymentmethods;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\InstallHelper;
use PaynlPayment\Shopware6\Helper\SettingsHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConfigControllerBase extends AbstractController
{
    public $installHelper;
    private $config;
    private $settingsHelper;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        InstallHelper $installHelper,
        Config $config,
        SettingsHelper $settingsHelper,
        LoggerInterface $logger
    ) {
        $this->installHelper = $installHelper;
        $this->config = $config;
        $this->settingsHelper = $settingsHelper;
        $this->logger = $logger;
    }

    /**
     * Shopware versions >= 6.4
     *
     * @Route(
     *     "/api/paynl/install-payment-methods",
     *     name="api.action.PaynlPayment.installPaymentMethodsSW64",
     *     methods={"GET"}
     *     )
     */
    public function installPaymentMethodsSW64(Request $request, Context $context): JsonResponse
    {
        return $this->getInstallPaymentMethodsResponse($request, $context);
    }

    /**
     * @Route(
     *     "/api/v{version}/paynl/install-payment-methods",
     *     name="api.action.PaynlPayment.installPaymentMethods",
     *     methods={"GET"}
     *     )
     */
    public function installPaymentMethods(Request $request, Context $context): JsonResponse
    {
        return $this->getInstallPaymentMethodsResponse($request, $context);
    }

    /**
     * Shopware versions >= 6.4
     *
     * @Route(
     *     "/api/paynl/store-settings",
     *     name="api.action.PaynlPayment.storeSettingsSW64",
     *     methods={"POST"}
     *     )
     */
    public function storeSettingsSW64(Request $request, Context $context): JsonResponse
    {
        return $this->getStoreSettingsResponse($request, $context);
    }

    /**
     * @Route(
     *     "/api/v{version}/paynl/store-settings",
     *     name="api.action.PaynlPayment.storeSettings",
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
     * @Route(
     *     "/api/v{version}/paynl/get-payment-terminals",
     *     name="api.action.PaynlPayment.get.payment-terminals.legacy",
     *     methods={"GET"}
     *     )
     */
    public function getPaymentTerminals(Request $request): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');

        $terminals = $this->settingsHelper->getTerminalsOptions($salesChannelId);

        return $this->json(['success' => true, 'data' => $terminals]);
    }

    /**
     * @Route(
     *     "/api/paynl/test-api-keys",
     *     name="api.action.PaynlPayment.test.api.keys",
     *     methods={"POST"}
     *     )
     * @Route(
     *     "/api/v{version}/paynl/test-api-keys",
     *     name="api.action.PaynlPayment.test.api.keys.legacy",
     *     methods={"POST"}
     *     )
     */
    public function testApiKeys(Request $request): JsonResponse
    {
        $tokenCode = $request->get('tokenCode');
        $apiToken = $request->get('apiToken');
        $serviceId = $request->get('serviceId');

        SDKConfig::setTokenCode($tokenCode);
        SDKConfig::setApiToken($apiToken);
        SDKConfig::setServiceId($serviceId);

        try {
            Paymentmethods::getList();

            return $this->json([
                'success' => true,
                'message' => "paynlValidation.messages.correctCredentials"
            ]);
        } catch (Exception $exception) {
            return $this->json([
                'success' => false,
                'message' => "paynlValidation.messages.wrongCredentials"
            ]);
        }
    }

    private function getInstallPaymentMethodsResponse(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $salesChannelsIds = empty($salesChannelId) ? $this->installHelper->getSalesChannels($context)->getIds()
            : [$salesChannelId];

        if ($this->isSinglePaymentMethod($salesChannelsIds)) {
            return new JsonResponse([]);
        }

        try {
            $this->installPaymentMethodsSalesChannels($context, $salesChannelsIds);

            return $this->json([
                'success' => true,
                'message' => "paynlValidation.messages.paymentMethodsSuccessfullyInstalled"
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error on installing payment methods', [
                'exception' => $e
            ]);

            return $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function installPaymentMethodsSalesChannels(Context $context, array $salesChannels)
    {
        foreach ($salesChannels as $salesChannelId) {
            $this->installHelper->installPaymentMethods($salesChannelId, $context);
            $this->installHelper->activatePaymentMethods($context);
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

    private function getStoreSettingsResponse(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $salesChannelId = $data['salesChannelId'] ?? '';
        $salesChannelsIds = empty($salesChannelId) ? $this->installHelper->getSalesChannels($context)->getIds()
            : [$salesChannelId];

        foreach ($salesChannelsIds as $salesChannelId) {
            if ($this->config->getSinglePaymentMethodInd($salesChannelId)) {
                $this->installHelper->addSinglePaymentMethod($salesChannelId, $context);

                $paymentMethodId = md5((string)InstallHelper::SINGLE_PAYMENT_METHOD_ID);
                $this->installHelper->setDefaultPaymentMethod($salesChannelId, $context, $paymentMethodId);

                continue;
            }

            $this->installHelper->removeSinglePaymentMethod($salesChannelId, $context);
        }

        return $this->json(['success' => true]);
    }
}
