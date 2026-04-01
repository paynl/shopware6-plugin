<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Notification;

use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Symfony\Component\HttpFoundation\Request;

class NotificationFacade
{
    private const REQUEST_ORDER_ID = 'orderId';
    private const REQUEST_OBJECT = 'object';
    private const REQUEST_STATUS = 'status';
    private const REQUEST_ACTION = 'action';
    private const PENDING_ACTION = 'pending';
    private const PENDING_RESPONSE = 'TRUE| Pending payment';

    private ProcessingHelper $processingHelper;

    public function __construct(ProcessingHelper $processingHelper)
    {
        $this->processingHelper = $processingHelper;
    }

    /**
     * Handles incoming PAY. notify callback and returns the response body.
     */
    public function onNotify(Request $request): string
    {
        $transactionId = $this->getTransactionIdFromRequest($request);
        $action = $this->getActionFromRequest($request);

        if ($action === self::PENDING_ACTION) {
            return self::PENDING_RESPONSE;
        }

        return $this->processingHelper->processNotify($transactionId);
    }

    private function getTransactionIdFromRequest(Request $request): string
    {
        $transactionId = $request->get('order_id', '');

        if ($transactionId !== '') {
            return (string) $transactionId;
        }

        $notifyObject = $request->get(self::REQUEST_OBJECT, []);

        return (string) ($notifyObject[self::REQUEST_ORDER_ID] ?? '');
    }

    private function getActionFromRequest(Request $request): string
    {
        $action = $request->get(self::REQUEST_ACTION, '');

        if ($action !== '') {
            return strtolower((string) $action);
        }

        $notifyObject = $request->get(self::REQUEST_OBJECT, []);
        $status = $notifyObject[self::REQUEST_STATUS] ?? [];

        return strtolower((string) ($status[self::REQUEST_ACTION] ?? ''));
    }
}
