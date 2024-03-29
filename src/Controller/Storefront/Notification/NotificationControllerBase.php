<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Notification;

use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationControllerBase extends StorefrontController
{
    private const REQUEST_ORDER_ID = 'orderId';
    private const REQUEST_OBJECT = 'object';
    private const REQUEST_STATUS = 'status';
    private const REQUEST_ACTION = 'action';

    /** @var ProcessingHelper */
    private $processingHelper;

    public function __construct(ProcessingHelper $processingHelper)
    {
        $this->processingHelper = $processingHelper;
    }

    protected function getNotifyResponse(Request $request): Response
    {
        $transactionId = $this->getNotifyRequestTransactionId($request);
        $action = $this->getNotifyRequestAction($request);

        if ($action == 'pending') {
            $responseText = 'TRUE| Pending payment';
        } else {
            $responseText = $this->processingHelper->processNotify($transactionId);
        }

        return new Response($responseText);
    }

    private function getNotifyRequestTransactionId(Request $request): string
    {
        $transactionId = $request->get('order_id', '');

        if (empty($transactionId)) {
            $notifyObject = $request->get(self::REQUEST_OBJECT, []);
            $transactionId = $notifyObject[self::REQUEST_ORDER_ID] ?? '';
        }

        return (string)$transactionId;
    }

    private function getNotifyRequestAction(Request $request): string
    {
        $action = $request->get(self::REQUEST_ACTION, '');

        if (empty($action)) {
            $notifyObject = $request->get(self::REQUEST_OBJECT, []);
            $status = $notifyObject[self::REQUEST_STATUS] ?? [];
            $action = $status[self::REQUEST_ACTION] ?? '';
        }

        return strtolower((string)$action);
    }
}
