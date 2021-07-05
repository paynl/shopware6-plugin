<?php

namespace PaynlPayment\Shopware6\ValueObjects;

use Shopware\Core\Framework\Struct\Struct;

class CustomPageDataValueObject extends Struct
{
    /**
     * @var array
     */
    private $configs;

    public function __construct(array $configs)
    {
        $this->configs = $configs;
    }

    public function getConfigs(): array
    {
        return $this->configs;
    }
}
