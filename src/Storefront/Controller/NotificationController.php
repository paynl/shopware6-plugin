<?php declare(strict_types=1);

namespace PaynlPayment\Storefront\Controller;

use function GuzzleHttp\Psr7\parse_query;
use PaynlPayment\Components\Api;
use PaynlPayment\Helper\ProcessingHelper;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
     * @Route("/PaynlPayment/notify",
     *     name="frontend.PaynlPayment.notify",
     *     defaults={"csrf_protected"=false},
     *     options={"seo"="false"},
     *     methods={"POST", "GET"}
     *     )
     */
    public function notify(Request $request): JsonResponse
    {
        $transactionId = $request->get('order_id', '');
        $responseText = $this->processingHelper->processNotify($transactionId);
        die($responseText);
    }
}
