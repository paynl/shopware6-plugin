<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\InstallHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @RouteScope(scopes={"api"})
 */
class ConfigController extends AbstractController
{
    public $installHelper;
    private $config;
    private $api;

    public function __construct(
        InstallHelper $installHelper,
        Config $config,
        Api $api
    ) {
        $this->installHelper = $installHelper;
        $this->config = $config;
        $this->api = $api;
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
        if ($this->config->getSinglePaymentMethodInd()) {
            return new JsonResponse([]);
        }

        try {
            $this->installHelper->addPaymentMethods($context);
            $this->installHelper->activatePaymentMethods($context);

            return $this->json([
                'success' => true,
                'message' => "paynlValidation.messages.paymentMethodsSuccessfullyInstalled"
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
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
        $data = json_decode($request->getContent(), true);
        if (empty($data['tokenCode']) || empty($data['apiToken']) || empty($data['serviceId'])) {
            return $this->json([
                'success' => false,
                'message' => "paynlValidation.messages.emptyCredentialsError"
            ]);
        }

        $isValidCredentials = $this->api->isValidCredentials($data['tokenCode'], $data['apiToken'], $data['serviceId']);
        if ($isValidCredentials) {
            $this->config->storeConfigData($data);

            if ($this->config->getSinglePaymentMethodInd()) {
                $this->installHelper->addSinglePaymentMethod($context);
                $this->installHelper->setDefaultPaymentMethod(
                    $context,
                    md5((string)InstallHelper::SINGLE_PAYMENT_METHOD_ID)
                );
            } else {
                $this->installHelper->removeSinglePaymentMethod($context);
                $this->installHelper->setDefaultPaymentMethod($context);
            }

            return $this->json([
                'success' => true,
                'message' => "paynlValidation.messages.settingsSavedSuccessfully"
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => "paynlValidation.messages.wrongCredentials"
        ]);
    }
}
