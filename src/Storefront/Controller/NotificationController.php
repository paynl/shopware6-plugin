<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Storefront\Controller;

use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
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
     * @Route("/PaynlPayment/notify",
     *     name="frontend.PaynlPayment.notify",
     *     defaults={"csrf_protected"=false},
     *     options={"seo"="false"},
     *     methods={"POST", "GET"}
     *     )
     */
    public function notify(Request $request): Response
    {
        $transactionId = $request->get('order_id', '');
        $action = $request->get('action', '');

        if ($action == 'pending') {
            $responseText = 'TRUE| Pending payment';
        } else {
            $responseText = $this->processingHelper->processNotify($transactionId);
        }

        return new Response($responseText);
    }
}
