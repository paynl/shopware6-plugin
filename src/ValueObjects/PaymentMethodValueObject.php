<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects;

use PaynlPayment\Shopware6\Components\Api;

class PaymentMethodValueObject
{
    public const TECHNICAL_NAME_PREFIX = 'paynl_';

    private $id;
    private $hashedId;
    private $name;
    private $visibleName;
    private $banks;
    private $brandId;
    private $description;

    public function __construct(array $paymentMethod)
    {
        $brand = $paymentMethod[Api::PAYMENT_METHOD_BRAND] ?? [];

        $this->id = (int)$paymentMethod[Api::PAYMENT_METHOD_ID];
        $this->hashedId = md5($paymentMethod[Api::PAYMENT_METHOD_ID]);
        $this->name = (string)$paymentMethod[Api::PAYMENT_METHOD_NAME];
        $this->visibleName = (string)$paymentMethod[Api::PAYMENT_METHOD_VISIBLE_NAME];
        $this->banks = $paymentMethod[Api::PAYMENT_METHOD_BANKS] ?? [];
        $this->brandId = (int)($brand[Api::PAYMENT_METHOD_BRAND_ID] ?? null);
        $this->description = (string)($brand[Api::PAYMENT_METHOD_BRAND_DESCRIPTION] ?? null);
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

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME_PREFIX . $this->getName();
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
     * @return null|int
     */
    public function getBrandId(): ?int
    {
        return $this->brandId;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }
}
