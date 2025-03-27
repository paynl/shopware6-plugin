<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\Event;

use PaynlPayment\Shopware6\ValueObjects\PAY\ArrayDataMapperInterface;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturn\OrderReturnEntity;

class OrderReturnPayloadMapper implements ArrayDataMapperInterface
{
    public const ID = 'id';
    public const ORDER_ID = 'orderId';
    public const STATE_ID = 'stateId';
    public const CREATED_BY_ID = 'createdById';
    public const RETURN_NUMBER = 'returnNumber';
    public const REQUESTED_AT = 'requestedAt';
    public const AMOUNT_TOTAL = 'amountTotal';
    public const AMOUNT_NET = 'amountNet';
    public const INTERNAL_COMMENT = 'internalComment';
    public const CREATED_AT = 'createdAt';

    public function mapArray(array $data): OrderReturnWrittenPayload
    {
        return new OrderReturnWrittenPayload(
            (string) $this->getArrayItemOrNull($data, self::ID),
            (string) $this->getArrayItemOrNull($data, self::ORDER_ID),
            (string) $this->getArrayItemOrNull($data, self::STATE_ID),
            (float) $this->getArrayItemOrNull($data, self::AMOUNT_TOTAL),
            (float) $this->getArrayItemOrNull($data, self::AMOUNT_NET),
            $this->getArrayItemOrNull($data, self::CREATED_BY_ID) ? (string) $this->getArrayItemOrNull($data, self::CREATED_BY_ID) : null,
            $this->getArrayItemOrNull($data, self::CREATED_AT),
            $this->getArrayItemOrNull($data, self::RETURN_NUMBER) ? (string) $this->getArrayItemOrNull($data, self::RETURN_NUMBER) : null,
            $this->getArrayItemOrNull($data, self::REQUESTED_AT),
            $this->getArrayItemOrNull($data, self::INTERNAL_COMMENT) ? (string) $this->getArrayItemOrNull($data, self::INTERNAL_COMMENT) : null,
        );
    }

    /** @param OrderReturnEntity $orderReturn */
    public function mapOrderReturnEntity($orderReturn): OrderReturnWrittenPayload
    {
        return new OrderReturnWrittenPayload(
            $orderReturn->getId(),
            $orderReturn->getOrderId(),
            $orderReturn->getStateId(),
            $orderReturn->getAmountTotal(),
            $orderReturn->getAmountNet(),
            $orderReturn->getCreatedById(),
            $orderReturn->getCreatedAt(),
            $orderReturn->getReturnNumber(),
            $orderReturn->getRequestedAt(),
            $orderReturn->getInternalComment(),
        );
    }


    private function getArrayItemOrNull(array $data, string $key)
    {
        return $data[$key] ?? null;
    }
}