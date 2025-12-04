<?php

declare(strict_types=1);

namespace OnChainDB\Http;

/**
 * HTTP Client Interface for dependency injection
 */
interface HttpClientInterface
{
    /**
     * Make a POST request
     *
     * @param string $url The URL to post to
     * @param array<string, mixed> $data The data to send
     * @param array<string, string> $headers Additional headers
     * @return array<string, mixed> The response data
     */
    public function post(string $url, array $data, array $headers = []): array;

    /**
     * Make a GET request
     *
     * @param string $url The URL to get
     * @param array<string, string> $headers Additional headers
     * @return array<string, mixed> The response data
     */
    public function get(string $url, array $headers = []): array;

    /**
     * Make a DELETE request
     *
     * @param string $url The URL to delete
     * @param array<string, string> $headers Additional headers
     * @return array<string, mixed> The response data
     */
    public function delete(string $url, array $headers = []): array;
}
