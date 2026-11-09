<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\InputBag;
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

        return $this->getPostedData()->get($name);
    }

    /**
     * Tells a submitted empty value apart from a field that was not submitted at all.
     */
    public function hasDataBagItem(string $name, RequestDataBag $dataBag): bool
    {
        return $dataBag->has($name) || $this->getPostedData()->has($name);
    }

    public function getDataBagArray(string $name, RequestDataBag $dataBag)
    {
        if ($dataBag->all($name)) {
            $data = $dataBag->all($name);
        }

        if (is_array($data = $data[$name] ?? null)) {
            return $data;
        }

        return $this->getPostedData()->all($name);
    }

    private function getPostedData(): InputBag
    {
        return (new Request($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER))->request;
    }
}
