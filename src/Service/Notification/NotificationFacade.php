<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Notification;

use PayNL\Sdk\Util\Exchange;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class NotificationFacade
{
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
        $payload = $this->buildPayloadFromRequest($request);
        $exchange = new Exchange($payload);

        try {
            $payOrder = $exchange->process();

            if ($payOrder->isPending()) {
                return $exchange->setResponse(true, 'Pending payment', true);
            }

            $notifyResult = $this->processingHelper->processNotify($payOrder->getOrderId());
            [$responseResult, $responseMessage] = $notifyResult;
        } catch (Throwable $exception) {
            $responseResult = false;
            $responseMessage = $exception->getMessage();
        }

        return $exchange->setResponse($responseResult, $responseMessage, true);
    }

    private function buildPayloadFromRequest(Request $request): array
    {
        $rawBody = $request->getContent();

        if (!empty($rawBody)) {
            $decoded = json_decode($rawBody, true, 512, JSON_BIGINT_AS_STRING);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['object'])) {
                return $decoded;
            }
        }

        $all = array_merge(
            $request->query->all(),
            $request->request->all()
        );

        if (!empty($all)) {
            return $all;
        }

        if (!empty($decoded) && is_array($decoded)) {
            return $decoded;
        }

        return [];
    }
}