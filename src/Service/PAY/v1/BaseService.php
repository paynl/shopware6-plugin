<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PAY\v1;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

abstract class BaseService
{
    protected const BASE_URL = 'https://connect.payments.nl';

    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    protected function request(string $method, string $url, string $bearerToken, array $data = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => "Bearer {$bearerToken}",
                'Content-Type' => 'application/json'
            ],
        ];

        if ($data) {
            $options['json'] = $data;
        }

        try {
            $response = $this->client->request($method, $this->getFullRequestUrl($url), $options);
        } catch (GuzzleException $exception) {
            return [
                'errorMessage' => $exception->getMessage(),
                'errorCode' => $exception->getCode(),
            ];
        }

        return $this->getResponseArray($response);
    }

    protected function getBaseUrl(): string
    {
        return static::BASE_URL;
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
