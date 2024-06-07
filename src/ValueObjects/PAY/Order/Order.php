<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Order;

class Order
{
    /** @var Product[]|null */
    private ?array $products;

    /**
     * @param Product[]|null $products
     */
    public function __construct(?array $products)
    {
        $this->products = $products;
    }

    /**
     * @return Product[]|null
     */
    public function getProducts(): ?array
    {
        return $this->products;
    }

    public function toArray()
    {
        $data = [];
        foreach ($this->products as $product) {
            $data[] = $product->toArray();
        }

        return [
            'products' => $data
        ];
    }
}