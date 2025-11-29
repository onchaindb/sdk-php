<?php

declare(strict_types=1);

namespace OnChainDB\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OnChainDB\Exception\OnChainDBException;

/**
 * Guzzle-based HTTP client implementation
 */
class GuzzleHttpClient implements HttpClientInterface
{
    private Client $client;
    /** @var array<string, string> */
    private array $defaultHeaders;

    /**
     * @param array<string, string> $defaultHeaders Default headers to include with every request
     * @param Client|null $client Optional pre-configured Guzzle client
     */
    public function __construct(array $defaultHeaders = [], ?Client $client = null)
    {
        $this->defaultHeaders = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $defaultHeaders);

        $this->client = $client ?? new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function post(string $url, array $data, array $headers = []): array
    {
        try {
            $response = $this->client->post($url, [
                'json' => $data,
                'headers' => array_merge($this->defaultHeaders, $headers),
            ]);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new OnChainDBException('Invalid JSON response from server');
            }

            return $decoded;
        } catch (GuzzleException $e) {
            throw new OnChainDBException(
                'HTTP request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $url, array $headers = []): array
    {
        try {
            $response = $this->client->get($url, [
                'headers' => array_merge($this->defaultHeaders, $headers),
            ]);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new OnChainDBException('Invalid JSON response from server');
            }

            return $decoded;
        } catch (GuzzleException $e) {
            throw new OnChainDBException(
                'HTTP request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
