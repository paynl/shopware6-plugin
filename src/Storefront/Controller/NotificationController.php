<?php declare(strict_types=1);

namespace PaynlPayment\Storefront\Controller;

use PaynlPayment\Components\Api;
use PaynlPayment\Helper\ProcessingHelper;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class NotificationController extends StorefrontController
{
    /** @var ProcessingHelper */
    private $processingHelper;

    public function __construct(ProcessingHelper $processingHelper)
    {
        $this->processingHelper = $processingHelper;
    }

    /**
     * @Route("/PaynlPayment/notify", name="frontend.PaynlPayment.notify", options={"seo"="false"}, methods={"POST"})
     */
    public function notify(Request $request): JsonResponse
    {
        $action = $request->get('action', '');
        $responseText = '';
        if (strtolower($action) !== Api::ACTION_PENDING) {
            $transactionId = $request->get('order_id', '');
            $responseText = $this->processingHelper->processNotify($transactionId);
        }
        $response = new JsonResponse();

        return $response->setContent('TRUE| '. $responseText);
    }
}
