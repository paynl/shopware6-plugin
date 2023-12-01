<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Notification;

use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationControllerBase extends StorefrontController
{
    /** @var ProcessingHelper */
    private $processingHelper;

    public function __construct(ProcessingHelper $processingHelper)
    {
        $this->processingHelper = $processingHelper;
    }

    /**
     * @Route(
     *     "/PaynlPayment/notify",
     *     name="frontend.PaynlPayment.notify",
     *     options={"seo"="false"},
     *     methods={"POST", "GET"},
     *     defaults={"csrf_protected"=false, "_routeScope"={"storefront"}}
     * )
     */
    public function notify(Request $request): Response
    {
        $transactionId = $request->get('order_id', '');
        if (empty($transactionId)) {
            $transactionId = $request->get('orderId', '');
        }

        $action = $request->get('action', '');

        if ($action == 'pending') {
            $responseText = 'TRUE| Pending payment';
        } else {
            $responseText = $this->processingHelper->processNotify($transactionId);
        }

        return new Response($responseText);
    }
}
