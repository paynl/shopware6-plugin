<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\InstallHelper;
use PaynlPayment\Shopware6\Helper\LogHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @RouteScope(scopes={"api"})
 */
class ConfigController extends AbstractController
{
    public $installHelper;
    private $config;
    private $api;
    private $translator;

    public function __construct(
        InstallHelper $installHelper,
        Config $config,
        Api $api,
        TranslatorInterface $translator
    ) {
        $this->installHelper = $installHelper;
        $this->config = $config;
        $this->api = $api;
        $this->translator = $translator;
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
        try {
            $this->installHelper->addPaymentMethods($context);
            $this->installHelper->activatePaymentMethods($context);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans("paynl.messages.paymentMethodsSuccessfullyInstalled")
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
                'message' => $this->translator->trans("paynl.messages.emptyCredentialsError")
            ]);
        }

        $isValidCredentials = $this->api->isValidCredentials($data['tokenCode'], $data['apiToken'], $data['serviceId']);
        if ($isValidCredentials) {
            $this->config->storeConfigData($data);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans("paynl.messages.settingsSavedSuccessfully")
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => $this->translator->trans("paynl.messages.wrongCredentials")
        ]);
    }
}
