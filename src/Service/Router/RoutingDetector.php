<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Router;

use Symfony\Component\HttpFoundation\RequestStack;

class RoutingDetector
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function isAdminApiRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return false;
        }

        return strpos($request->getPathInfo(), '/api/paynl/') !== false;
    }

    public function isStoreApiRoute(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return false;
        }

        return strpos($request->getPathInfo(), '/store-api') === 0;
    }

    public function isStorefrontRoute(): bool
    {
        return !$this->isAdminApiRoute() && !$this->isStoreApiRoute();
    }
}
