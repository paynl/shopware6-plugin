<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayPal\v2;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PaynlPayment\Shopware6\Exceptions\PayPalPaymentApi;
use Psr\Http\Message\ResponseInterface;

abstract class BaseService
{
    protected const METHOD_GET = 'GET';
    protected const METHOD_POST = 'POST';
    protected const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';

    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /** @throws PayPalPaymentApi */
    protected function request(string $method, string $url, string $basicToken, array $data = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => "Basic {$basicToken}",
                'Content-Type' => 'application/json'
            ],
        ];

        if ($data) {
            $options['json'] = $data;
        }

        try {
            $response = $this->client->request($method, $this->getFullRequestUrl($url), $options);

            return $this->getResponseArray($response);
        } catch (GuzzleException $exception) {
            throw PayPalPaymentApi::paymentResponseError($exception->getMessage(), $exception->getCode());
        }
    }

    protected function getBaseUrl(): string
    {
        return static::SANDBOX_URL;
    }

    protected function getFullRequestUrl(string $url): string
    {
        return sprintf('%s/%s', $this->getBaseUrl(), $url);
    }

    protected function getResponseArray(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }
}