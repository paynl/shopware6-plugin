<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;

class PriceDefinitionInstance extends Exception
{
    public static function unknownPriceDefinitionProvided(): static
    {
        return new static('Unknown price definition instance provided');
    }
}
