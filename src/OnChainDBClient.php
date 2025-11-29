<?php

declare(strict_types=1);

namespace OnChainDB;

use OnChainDB\Http\HttpClientInterface;
use OnChainDB\Http\GuzzleHttpClient;
use OnChainDB\Query\QueryBuilder;

/**
 * Main client for interacting with OnChainDB
 */
class OnChainDBClient
{
    private HttpClientInterface $httpClient;
    private string $endpoint;
    private string $appId;
    private string $appKey;
    private ?string $userKey;

    public function __construct(
        string $endpoint,
        string $appId,
        string $appKey,
        ?string $userKey = null,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->endpoint = rtrim($endpoint, '/');
        $this->appId = $appId;
        $this->appKey = $appKey;
        $this->userKey = $userKey;

        error_log("[OnChainDB] Initializing client");
        error_log("[OnChainDB] Endpoint: {$this->endpoint}");
        error_log("[OnChainDB] App ID: {$appId}");
        error_log("[OnChainDB] App Key: " . (strlen($appKey) > 0 ? 'SET (' . strlen($appKey) . ' chars)' : 'NOT SET'));

        // Build default headers for authentication
        $headers = [
            'Content-Type' => 'application/json',
            'X-App-Key' => $appKey,
        ];

        if ($userKey !== null) {
            $headers['X-User-Key'] = $userKey;
        }

        $this->httpClient = $httpClient ?? new GuzzleHttpClient($headers);
        error_log("[OnChainDB] Client initialized successfully");
    }

    /**
     * Create a new query builder instance
     */
    public function queryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->httpClient, $this->endpoint, $this->appId);
    }

    /**
     * Find a single document by query (Prisma-style findUnique)
     * Returns the latest record by metadata (updatedAt or createdAt)
     *
     * @param string $collection Collection name
     * @param array<string, mixed> $where Query conditions
     * @return array<string, mixed>|null
     */
    public function findUnique(string $collection, array $where): ?array
    {
        $query = $this->queryBuilder()->collection($collection);

        foreach ($where as $field => $value) {
            $query = $query->whereField($field)->equals($value);
        }

        return $query->selectAll()->executeUnique();
    }

    /**
     * Find multiple documents by query
     *
     * @param string $collection Collection name
     * @param array<string, mixed> $where Query conditions
     * @param int|null $limit Maximum records to return
     * @param int|null $offset Records to skip
     * @return array<int, array<string, mixed>>
     */
    public function findMany(
        string $collection,
        array $where = [],
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $query = $this->queryBuilder()->collection($collection);

        foreach ($where as $field => $value) {
            $query = $query->whereField($field)->equals($value);
        }

        if ($limit !== null) {
            $query = $query->limit($limit);
        }

        if ($offset !== null) {
            $query = $query->offset($offset);
        }

        $response = $query->selectAll()->execute();
        return $response['records'] ?? [];
    }

    /**
     * Count documents matching criteria
     *
     * @param string $collection Collection name
     * @param array<string, mixed> $where Query conditions
     */
    public function countDocuments(string $collection, array $where = []): int
    {
        $query = $this->queryBuilder()->collection($collection);

        foreach ($where as $field => $value) {
            $query = $query->whereField($field)->equals($value);
        }

        return $query->count();
    }

    /**
     * Store documents in a collection
     *
     * @param string $collection Collection name
     * @param array<int, array<string, mixed>> $data Documents to store
     * @param array<string, mixed> $paymentProof Payment proof for the transaction
     * @param bool $waitForConfirmation Whether to wait for blockchain confirmation (default: true)
     * @param int $pollIntervalMs Polling interval in milliseconds (default: 2000)
     * @param int $maxWaitTimeMs Maximum wait time in milliseconds (default: 600000 = 10 minutes)
     */
    public function store(
        string $collection,
        array $data,
        array $paymentProof,
        bool $waitForConfirmation = true,
        int $pollIntervalMs = 2000,
        int $maxWaitTimeMs = 600000
    ): array {
        // Build root in format appId::collection
        $root = "{$this->appId}::{$collection}";

        $payload = [
            'root' => $root,
            'data' => $data,
            ...$paymentProof
        ];

        $url = "{$this->endpoint}/store";
        error_log("[OnChainDB] Store request to root: {$root}");
        error_log("[OnChainDB] POST {$url}");
        error_log("[OnChainDB] Payload: " . json_encode($payload, JSON_PRETTY_PRINT));

        try {
            $response = $this->httpClient->post($url, $payload);
            error_log("[OnChainDB] Store response: " . json_encode($response, JSON_PRETTY_PRINT));

            // Check if we got an async response with ticket_id
            if (isset($response['ticket_id']) && $waitForConfirmation) {
                $ticketId = $response['ticket_id'];
                error_log("[OnChainDB] Got ticket {$ticketId}, waiting for completion...");

                // Poll for task completion
                $taskInfo = $this->waitForTaskCompletion($ticketId, $pollIntervalMs, $maxWaitTimeMs);

                // Extract the actual storage result from the completed task
                if (isset($taskInfo['result'])) {
                    return $taskInfo['result'];
                }

                return $taskInfo;
            }

            return $response;
        } catch (\Exception $e) {
            error_log("[OnChainDB] Store failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get task status by ticket ID
     *
     * @param string $ticketId The ticket ID returned from async operations
     * @return array<string, mixed> Task info including status, result, etc.
     */
    public function getTaskStatus(string $ticketId): array
    {
        $url = "{$this->endpoint}/task/{$ticketId}";
        error_log("[OnChainDB] GET {$url}");

        try {
            $response = $this->httpClient->get($url);
            error_log("[OnChainDB] Task status: " . json_encode($response, JSON_PRETTY_PRINT));
            return $response;
        } catch (\Exception $e) {
            error_log("[OnChainDB] Get task status failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Wait for a task to complete by polling
     *
     * @param string $ticketId The ticket ID to poll
     * @param int $pollIntervalMs Polling interval in milliseconds (default: 2000)
     * @param int $maxWaitTimeMs Maximum wait time in milliseconds (default: 600000 = 10 minutes)
     * @return array<string, mixed> The completed task info
     * @throws \RuntimeException If task fails or times out
     */
    public function waitForTaskCompletion(
        string $ticketId,
        int $pollIntervalMs = 2000,
        int $maxWaitTimeMs = 600000
    ): array {
        $startTime = microtime(true) * 1000;
        $pollIntervalSec = $pollIntervalMs / 1000;

        error_log("[OnChainDB] Waiting for task {$ticketId} to complete...");

        while ((microtime(true) * 1000) - $startTime < $maxWaitTimeMs) {
            try {
                $taskInfo = $this->getTaskStatus($ticketId);

                // Get status (can be string or object like {"Failed": {"error": "..."}})
                $status = $taskInfo['status'] ?? null;

                if (is_string($status)) {
                    error_log("[OnChainDB] Task {$ticketId} status: {$status}");

                    // Check if completed
                    if ($status === 'Completed') {
                        error_log("[OnChainDB] Task {$ticketId} completed successfully");
                        return $taskInfo;
                    }

                    // Check for error status
                    if (stripos($status, 'error') !== false || stripos($status, 'failed') !== false) {
                        throw new \RuntimeException("Task failed: {$status}");
                    }
                } elseif (is_array($status)) {
                    error_log("[OnChainDB] Task {$ticketId} status: " . json_encode($status));

                    // Check for Failed status object
                    if (isset($status['Failed'])) {
                        $error = $status['Failed']['error'] ?? 'Unknown error';
                        throw new \RuntimeException("Task failed: {$error}");
                    }
                }

                // Still in progress, wait before next poll
                usleep((int)($pollIntervalMs * 1000)); // usleep takes microseconds

            } catch (\RuntimeException $e) {
                // Re-throw runtime exceptions (task failures)
                throw $e;
            } catch (\Exception $e) {
                error_log("[OnChainDB] Error polling task {$ticketId}: " . $e->getMessage());
                // Continue polling on transient errors
                usleep((int)($pollIntervalMs * 1000));
            }
        }

        throw new \RuntimeException("Task {$ticketId} timed out after " . ($maxWaitTimeMs / 1000) . " seconds");
    }

    /**
     * Get the endpoint URL
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get the app ID
     */
    public function getAppId(): string
    {
        return $this->appId;
    }

    /**
     * Get the app key
     */
    public function getAppKey(): string
    {
        return $this->appKey;
    }

    /**
     * Get the user key (if set)
     */
    public function getUserKey(): ?string
    {
        return $this->userKey;
    }
}
