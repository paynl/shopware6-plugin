<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY;

use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Amount;
use PaynlPayment\Shopware6\ValueObjects\PAY\Response\Links;
use PaynlPayment\Shopware6\ValueObjects\PAY\Response\Status;
use PaynlPayment\Shopware6\ValueObjects\PAY\Response\CreateOrderResponse;

class OrderDataMapper implements ArrayDataMapperInterface
{
    public function mapArray(array $data): CreateOrderResponse
    {
        $status = new Status(
            (int) ($data['status']['code'] ?? null),
            (string) ($data['status']['action'] ?? null),
        );

        $amount = new Amount(
            (int) ($data['amount']['value']),
            (string) ($data['amount']['currency']),
        );

        $authorizedAmount = new Amount(
            (int) ($data['authorizedAmount']['value']),
            (string) ($data['authorizedAmount']['currency']),
        );

        $capturedAmount = new Amount(
            (int) ($data['capturedAmount']['value']),
            (string) ($data['capturedAmount']['currency']),
        );

        $links = new Links(
            (string) ($data['links']['abort'] ?? null),
            (string) ($data['links']['status'] ?? null),
            (string) ($data['links']['redirect'] ?? null)
        );

        return new CreateOrderResponse(
            (string) $data['id'],
            (string) $data['serviceId'],
            (string) $data['reference'],
            (string) $data['orderId'],
            (string) $data['uuid'],
            $status,
            $amount,
            $authorizedAmount,
            $capturedAmount,
            $links
        );
    }
}