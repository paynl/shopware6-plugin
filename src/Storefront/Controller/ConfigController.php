<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Storefront\Controller;

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

    public function __construct(InstallHelper $installHelper)
    {
        $this->installHelper = $installHelper;
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

            return $this->json(['message' => "Payment methods were successfully added."]);
        } catch (\Exception $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }
    }
}
