<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects;

interface ArrayDataMapperInterface
{
    public function mapArray(array $data);
}