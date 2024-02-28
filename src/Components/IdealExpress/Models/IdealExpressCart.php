<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\IdealExpress\Models;

class IdealExpressCart
{
    /**
     * @var IdealExpressLineItem[]
     */
    private $items;

    /**
     * @var IdealExpressLineItem[]
     */
    private $shippings;

    /**
     * @var ?IdealExpressLineItem
     */
    private $taxes;


    /**
     *
     */
    public function __construct()
    {
        $this->items = [];

        $this->shippings = [];
        $this->taxes = null;
    }

    /**
     * @return IdealExpressLineItem[]
     */
    public function getShippings(): array
    {
        return $this->shippings;
    }

    /**
     * @return null|IdealExpressLineItem
     */
    public function getTaxes(): ?IdealExpressLineItem
    {
        return $this->taxes;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        $amount = $this->getProductAmount();
        $amount += $this->getShippingAmount();

        return $amount;
    }

    /**
     * @return float
     */
    public function getProductAmount(): float
    {
        $amount = 0;

        /** @var IdealExpressLineItem $item */
        foreach ($this->items as $item) {
            $amount += ($item->getQuantity() * $item->getPrice());
        }

        return $amount;
    }

    /**
     * @return float
     */
    public function getShippingAmount(): float
    {
        $amount = 0;

        /** @var IdealExpressLineItem $item */
        foreach ($this->shippings as $item) {
            $amount += ($item->getQuantity() * $item->getPrice());
        }

        return $amount;
    }

    /**
     * @return IdealExpressLineItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param string $number
     * @param string $name
     * @param int $quantity
     * @param float $price
     */
    public function addItem(string $number, string $name, int $quantity, float $price): void
    {
        $this->items[] = new IdealExpressLineItem($number, $name, $quantity, $price);
    }

    /**
     * @param string $name
     * @param float $price
     */
    public function addShipping(string $name, float $price): void
    {
        $this->shippings[] = new IdealExpressLineItem("SHIPPING", $name, 1, $price);
    }

    /**
     * @param float $price
     */
    public function setTaxes(float $price): void
    {
        $this->taxes = new IdealExpressLineItem("TAXES", '', 1, $price);
    }
}
