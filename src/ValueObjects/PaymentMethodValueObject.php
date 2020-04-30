<?php

namespace PaynlPayment\Shopware6\ValueObjects;


use PaynlPayment\Shopware6\Components\Api;

class PaymentMethodValueObject
{
    private $id;
    private $hashedId;
    private $name;
    private $visibleName;

    public function __construct(array $paymentMethod)
    {
        $this->id = $paymentMethod[Api::PAYMENT_METHOD_ID];
        $this->hashedId = md5($paymentMethod[Api::PAYMENT_METHOD_ID]);
        $this->name = $paymentMethod[Api::PAYMENT_METHOD_NAME];
        $this->visibleName = $paymentMethod[Api::PAYMENT_METHOD_VISIBLE_NAME];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getHashedId()
    {
        return $this->hashedId;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getVisibleName()
    {
        return $this->visibleName;
    }
}