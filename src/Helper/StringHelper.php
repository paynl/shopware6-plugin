<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

class StringHelper
{
    public function endsWith(string $haystack, string $needle )
    {
        $length = strlen($needle);
        if (!$length) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }
}
