<?php

namespace PaynlPayment\Shopware6\ValueObjects;

use PaynlPayment\Shopware6\Components\Api;

class PaymentMethodValueObject
{
    private $id;
    private $hashedId;
    private $name;
    private $visibleName;
    private $banks;
    private $description;

    public function __construct(array $paymentMethod)
    {
        $this->id = $paymentMethod[Api::PAYMENT_METHOD_ID];
        $this->hashedId = md5($paymentMethod[Api::PAYMENT_METHOD_ID]);
        $this->name = $paymentMethod[Api::PAYMENT_METHOD_NAME];
        $this->visibleName = $paymentMethod[Api::PAYMENT_METHOD_VISIBLE_NAME];
        $this->banks = $paymentMethod[Api::PAYMENT_METHOD_BANKS] ?: [];
        $this->description = $paymentMethod[Api::PAYMENT_METHOD_BRAND][Api::PAYMENT_METHOD_BRAND_DESCRIPTION];
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getHashedId(): string
    {
        return $this->hashedId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getVisibleName(): string
    {
        return $this->visibleName;
    }

    /**
     * @return mixed[]
     */
    public function getBanks(): array
    {
        return $this->banks;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }
}
