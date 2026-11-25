<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Notification;

use Exception;
use PayNL\Sdk\Model\Pay\PayOrder;
use PayNL\Sdk\Model\Pay\PayStatus;
use PayNL\Sdk\Model\Request\TransactionStatusRequest;
use PayNL\Sdk\Util\Exchange;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class NotificationFacade
{
    public function __construct(
        private ProcessingHelper $processingHelper,
        private Api $api,
        private Config $config,
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

        // PAY.Parts webhooks are signed with the sales location key (SL-...), which the SDK can't verify:
        // it only accepts the API token code. We check those ourselves and take the status from the API.
        $salesLocationSigned = str_starts_with((string) $request->headers->get('signature-keyid'), 'SL-');

        try {
            $payOrder = $salesLocationSigned
                ? $this->processSalesLocationSigned($exchange, $paynlOrderId, $salesChannelId)
                : $exchange->process($sdkConfig);

            if ($payOrder->isPending()) {
                return $exchange->setResponse(true, 'Pending payment', true);
            }

            // Unsigned notify: the SDK read the status from the API, which can still return 95 right after
            // a capture (e.g. WERO). Answer FALSE so PAY. retries instead of storing the stale authorize state.
            if ((!$exchange->isSignExchange() || $salesLocationSigned)
                && $exchange->getPayLoad()->getInternalStateId() === PayStatus::PAID
                && $payOrder->getStatusCode() === PayStatus::AUTHORIZE
            ) {
                $this->logger->info('PAY. notify: paid notify not yet confirmed by API, asking PAY. to retry', [
                    'paynlOrderId' => $paynlOrderId,
                ]);

                return $exchange->setResponse(false, 'Paid status not yet confirmed, retry', true);
            }

            $notifyResult = $this->processingHelper->processNotify($payOrder->getOrderId(), $payOrder);
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

    /** @throws Exception when the signature does not match the sales location's code and secret */
    private function processSalesLocationSigned(Exchange $exchange, string $paynlOrderId, string $salesChannelId): PayOrder
    {
        $serviceId = trim($this->config->getServiceId($salesChannelId));
        if ($serviceId === '' || !$exchange->checkSignExchange($serviceId, $this->api->getServiceSecret($salesChannelId))) {
            throw new Exception('Signing request failed');
        }

        // Same choice as the SDK (Exchange::process): refunds need the refund-aware transaction status,
        // otherwise a refunded order could be set back to paid.
        if (stripos($exchange->getAction(), 'refund') !== false) {
            return (new TransactionStatusRequest($paynlOrderId))->setConfig($this->api->getConfig($salesChannelId))->start();
        }

        return $this->api->getOrderStatus($paynlOrderId, $salesChannelId);
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
