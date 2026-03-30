<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Router;

use Symfony\Component\Routing\RouterInterface;

class PaymentUrlBuilder
{
    private const ROUTE_PARAM_PAYMENT_TOKEN = '_sw_payment_token';

    private RouterInterface $router;

    private RoutingDetector $routingDetector;

    private ?string $envAppUrl;

    public function __construct(
        RouterInterface $router,
        RoutingDetector $routingDetector,
        ?string $envAppUrl = null
    ) {
        $this->router = $router;
        $this->routingDetector = $routingDetector;
        $this->envAppUrl = $envAppUrl ?? '';
    }

    public function buildReturnUrl(string $paymentToken): string
    {
        $params = [self::ROUTE_PARAM_PAYMENT_TOKEN => $paymentToken];

        if ($this->routingDetector->isStoreApiRoute()) {
            $url = $this->router->generate(
                'api.PaynlPayment.finalize-transaction',
                $params,
                RouterInterface::ABSOLUTE_URL
            );

            return $this->applyAdminDomain($url);
        }

        return (string) $this->router->generate(
            'frontend.PaynlPayment.finalize-transaction',
            $params,
            RouterInterface::ABSOLUTE_URL
        );
    }

    public function buildExchangeUrl(): string
    {
        if ($this->routingDetector->isStoreApiRoute()) {
            $url = $this->router->generate(
                'api.PaynlPayment.notify',
                [],
                RouterInterface::ABSOLUTE_URL
            );

            return $this->applyAdminDomain($url);
        }

        return (string) $this->router->generate(
            'frontend.PaynlPayment.notify',
            [],
            RouterInterface::ABSOLUTE_URL
        );
    }

    private function applyAdminDomain(string $url): string
    {
        $adminDomain = trim((string) $this->envAppUrl);
        $adminDomain = str_replace(['http://', 'https://'], '', $adminDomain);

        if ($adminDomain === '' || $adminDomain === 'localhost') {
            return $url;
        }

        $components = parse_url($url);
        $host = (is_array($components) && isset($components['host'])) ? (string) $components['host'] : '';

        return str_replace($host, $adminDomain, $url);
    }
}
