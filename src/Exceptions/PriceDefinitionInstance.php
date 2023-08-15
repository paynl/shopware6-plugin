<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;

class PriceDefinitionInstance extends Exception
{
    public static function unknownPriceDefinitionProvided(): PriceDefinitionInstance
    {
        return new PriceDefinitionInstance('Unknown price definition instance provided');
    }
}
