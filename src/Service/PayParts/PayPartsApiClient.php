<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PaynlPayment\Shopware6\Exceptions\PayPartsApiException;

class PayPartsApiClient
{
    private const ENDPOINT_SESSION_START = 'v1/session/start';

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @throws PayPartsApiException
     */
    public function createSession(array $payload, string $baseUrl, string $basicToken): array
    {
        return $this->request('POST', self::ENDPOINT_SESSION_START, $baseUrl, $basicToken, $payload);
    }

    /**
     * @throws PayPartsApiException
     */
    private function request(string $method, string $endpoint, string $baseUrl, string $basicToken, array $payload = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Basic ' . $basicToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            // Without limits a slow PAY.Parts API would block the PHP worker; on timeout the caller gets a PayPartsApiException.
            'connect_timeout' => 5,
            'timeout'         => 15,
        ];

        if ($payload !== []) {
            $options['json'] = $payload;
        }

        try {
            $response = $this->client->request($method, rtrim($baseUrl, '/') . '/' . $endpoint, $options);

            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            throw PayPartsApiException::sessionCreateFailed($e->getMessage(), $e->getCode());
        }
    }
}