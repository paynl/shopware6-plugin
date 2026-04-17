<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Notification;

use PayNL\Sdk\Util\Exchange;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class NotificationFacade
{
    public function __construct(
        private ProcessingHelper $processingHelper,
        private Api $api,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Handles incoming PAY. notify callback and returns the response body.
     */
    public function onNotify(Request $request): string
    {
        $payload = $this->buildPayloadFromRequest($request);
        $exchange = new Exchange($payload);

        try {
            $paynlOrderId = $exchange->getPayOrderId();
        } catch (Throwable $e) {
            $this->logger->error('PAY. notify: could not read pay order id from payload', [
                'exception' => $e->getMessage(),
            ]);

            return $exchange->setResponse(false, 'Invalid payload', true);
        }

        if ($paynlOrderId === '') {
            $this->logger->warning('PAY. notify: empty pay order id');

            return $exchange->setResponse(false, 'Missing pay order id', true);
        }

        $salesChannelId = $this->processingHelper->getSalesChannelIdByPaynlTransactionId($paynlOrderId);
        if ($salesChannelId === null || $salesChannelId === '') {
            $this->logger->warning('PAY. notify: no local Paynl transaction for PAY order', [
                'paynlOrderId' => $paynlOrderId,
            ]);

            return $exchange->setResponse(false, 'Transaction not found', true);
        }

        $sdkConfig = $this->api->getConfig($salesChannelId);

        try {
            $payOrder = $exchange->process($sdkConfig);

            if ($payOrder->isPending()) {
                return $exchange->setResponse(true, 'Pending payment', true);
            }

            $notifyResult = $this->processingHelper->processNotify($payOrder->getOrderId());
            ['result' => $responseResult, 'message' => $responseMessage] = $notifyResult;
        } catch (Throwable $exception) {
            $this->logger->error('PAY. notify: processing failed', [
                'paynlOrderId' => $paynlOrderId,
                'exception' => $exception->getMessage(),
            ]);

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
