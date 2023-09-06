<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\Request;

class RequestDataBagHelper
{
    /**
     * On the edit order page, we don't get a correct DataBag with the issuer data. Therefore we need to get this
     * data from the $_POST/$_GET.
     */
    public function getDataBagItem(string $name, RequestDataBag $dataBag)
    {
        if ($dataBag->get($name)) {
            return $dataBag->get($name);
        }

        $request = (new Request($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER))->request;
        return $request->get($name);
    }

    public function getDataBagArray(string $name, RequestDataBag $dataBag)
    {
        if ($dataBag->all($name)) {
            return $dataBag->all($name);
        }

        $request = (new Request($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER))->request;
        return $request->all($name);
    }
}
