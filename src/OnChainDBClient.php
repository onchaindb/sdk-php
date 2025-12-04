<?php

declare(strict_types=1);

namespace OnChainDB;

use OnChainDB\Http\HttpClientInterface;
use OnChainDB\Http\GuzzleHttpClient;
use OnChainDB\Query\QueryBuilder;

/**
 * Main client for interacting with OnChainDB
 *
 * @see Types for PHPStan/Psalm type definitions
 *
 * @phpstan-import-type SimpleCollectionSchema from Types
 * @phpstan-import-type CreateCollectionResult from Types
 * @phpstan-import-type SyncCollectionResult from Types
 * @phpstan-import-type MaterializedView from Types
 * @phpstan-import-type ViewInfo from Types
 * @phpstan-import-type PricingQuoteResponse from Types
 * @phpstan-import-type PaymentProof from Types
 * @phpstan-import-type StoreResult from Types
 * @phpstan-import-type TaskInfo from Types
 * @phpstan-import-type QueryResult from Types
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
     * @param PaymentProof $paymentProof Payment proof for the transaction
     * @param bool $waitForConfirmation Whether to wait for blockchain confirmation (default: true)
     * @param int $pollIntervalMs Polling interval in milliseconds (default: 2000)
     * @param int $maxWaitTimeMs Maximum wait time in milliseconds (default: 600000 = 10 minutes)
     * @return StoreResult|TaskInfo
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
     * @return TaskInfo
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
     * @return TaskInfo The completed task info
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

    /**
     * Get pricing quote for an operation
     *
     * @param string $collection Collection name
     * @param string $operationType "write" or "read"
     * @param int $sizeKb Size in KB for the operation
     * @param int|null $monthlyVolumeKb Optional monthly volume for volume discounts
     * @return PricingQuoteResponse
     */
    public function getPricingQuote(
        string $collection,
        string $operationType = 'write',
        int $sizeKb = 1,
        ?int $monthlyVolumeKb = null
    ): array {
        $payload = [
            'app_id' => $this->appId,
            'operation_type' => $operationType,
            'size_kb' => $sizeKb,
            'collection' => $collection,
        ];

        if ($monthlyVolumeKb !== null) {
            $payload['monthly_volume_kb'] = $monthlyVolumeKb;
        }

        $url = "{$this->endpoint}/api/pricing/quote";
        return $this->httpClient->post($url, $payload);
    }

    // ========================================================================
    // Collection Schema Methods
    // ========================================================================

    /**
     * Base fields that are automatically indexed when useBaseFields is true
     */
    private const BASE_FIELDS = [
        'id' => ['type' => 'string', 'index' => true, 'unique' => true],
        'createdAt' => ['type' => 'date', 'index' => true],
        'updatedAt' => ['type' => 'date', 'index' => true],
        'deletedAt' => ['type' => 'date', 'index' => true],
    ];

    /**
     * Get default index type for a field type
     */
    private function getDefaultIndexType(string $fieldType): string
    {
        return match ($fieldType) {
            'string' => 'string',
            'number' => 'number',
            'boolean' => 'boolean',
            'date' => 'date',
            default => 'string',
        };
    }

    /**
     * Create a collection with schema-defined indexes
     *
     * @param SimpleCollectionSchema $schema Collection schema definition
     * @return CreateCollectionResult
     */
    public function createCollection(array $schema): array
    {
        $result = [
            'collection' => $schema['name'],
            'indexes' => [],
            'success' => true,
            'warnings' => [],
        ];

        // Merge base fields if enabled (default: true)
        $allFields = [];
        $useBaseFields = $schema['useBaseFields'] ?? true;

        if ($useBaseFields) {
            $allFields = self::BASE_FIELDS;
        }

        foreach ($schema['fields'] as $fieldName => $fieldDef) {
            $allFields[$fieldName] = $fieldDef;
        }

        // Create indexes only for fields marked with index: true
        foreach ($allFields as $fieldName => $fieldDef) {
            if (empty($fieldDef['index'])) {
                continue;
            }

            $indexType = $fieldDef['indexType'] ?? $this->getDefaultIndexType($fieldDef['type']);

            $indexRequest = [
                'name' => "{$schema['name']}_{$fieldName}_idx",
                'collection' => $schema['name'],
                'field_name' => $fieldName,
                'index_type' => $indexType,
                'store_values' => true,
            ];

            if (!empty($fieldDef['readPricing'])) {
                $pricingModel = !empty($fieldDef['readPricing']['pricePerKb']) ? 'per_kb' : 'per_access';
                $indexRequest['read_price_config'] = [
                    'pricing_model' => $pricingModel,
                    'price_per_access_tia' => $fieldDef['readPricing']['pricePerAccess'] ?? null,
                    'price_per_kb_tia' => $fieldDef['readPricing']['pricePerKb'] ?? null,
                ];
            }

            try {
                $url = "{$this->endpoint}/api/apps/{$this->appId}/indexes";
                $response = $this->httpClient->post($url, $indexRequest);

                $status = !empty($response['updated']) ? 'updated' : 'created';

                if (!empty($response['_warning'])) {
                    $result['warnings'][] = "{$fieldName}: {$response['_warning']}";
                }

                $result['indexes'][] = [
                    'field' => $fieldName,
                    'type' => $indexType,
                    'status' => $status,
                ];
            } catch (\Exception $e) {
                $result['indexes'][] = [
                    'field' => $fieldName,
                    'type' => $indexType,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $result['success'] = false;
            }
        }

        return $result;
    }

    /**
     * Sync collection schema - applies diff on indexes
     *
     * @param SimpleCollectionSchema $schema Updated collection schema definition
     * @return SyncCollectionResult
     */
    public function syncCollection(array $schema): array
    {
        $result = [
            'collection' => $schema['name'],
            'created' => [],
            'removed' => [],
            'unchanged' => [],
            'success' => true,
            'errors' => [],
        ];

        // Get existing indexes for this collection
        $existingIndexes = [];
        try {
            $url = "{$this->endpoint}/api/apps/{$this->appId}/collections/{$schema['name']}/indexes";
            $response = $this->httpClient->get($url);
            $indexes = $response['indexes'] ?? $response ?? [];

            foreach ($indexes as $idx) {
                $existingIndexes[$idx['field_name']] = [
                    'type' => $idx['index_type'],
                    'name' => $idx['name'],
                ];
            }
        } catch (\Exception $e) {
            // Collection might not exist yet, that's okay
        }

        // Merge base fields if enabled (default: true)
        $allFields = [];
        $useBaseFields = $schema['useBaseFields'] ?? true;

        if ($useBaseFields) {
            $allFields = self::BASE_FIELDS;
        }

        foreach ($schema['fields'] as $fieldName => $fieldDef) {
            $allFields[$fieldName] = $fieldDef;
        }

        // Build set of desired indexed fields
        $desiredIndexedFields = [];
        foreach ($allFields as $fieldName => $fieldDef) {
            if (!empty($fieldDef['index'])) {
                $desiredIndexedFields[$fieldName] = $fieldDef;
            }
        }

        // Find indexes to create (in desired but not existing)
        foreach ($desiredIndexedFields as $fieldName => $fieldDef) {
            if (!isset($existingIndexes[$fieldName])) {
                $indexType = $fieldDef['indexType'] ?? $this->getDefaultIndexType($fieldDef['type']);

                $indexRequest = [
                    'name' => "{$schema['name']}_{$fieldName}_idx",
                    'collection' => $schema['name'],
                    'field_name' => $fieldName,
                    'index_type' => $indexType,
                    'store_values' => true,
                ];

                // Add unique constraint if specified
                if (!empty($fieldDef['unique'])) {
                    $indexRequest['unique_constraint'] = true;
                }

                if (!empty($fieldDef['readPricing'])) {
                    $pricingModel = !empty($fieldDef['readPricing']['pricePerKb']) ? 'per_kb' : 'per_access';
                    $indexRequest['read_price_config'] = [
                        'pricing_model' => $pricingModel,
                        'price_per_access_tia' => $fieldDef['readPricing']['pricePerAccess'] ?? null,
                        'price_per_kb_tia' => $fieldDef['readPricing']['pricePerKb'] ?? null,
                    ];
                }

                try {
                    $url = "{$this->endpoint}/api/apps/{$this->appId}/indexes";
                    $this->httpClient->post($url, $indexRequest);
                    $result['created'][] = ['field' => $fieldName, 'type' => $indexType];
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to create index on {$fieldName}: {$e->getMessage()}";
                    $result['success'] = false;
                }
            }
        }

        // Find indexes to remove (existing but not in desired)
        foreach ($existingIndexes as $fieldName => $existing) {
            if (!isset($desiredIndexedFields[$fieldName])) {
                try {
                    // Index ID format: {collection}_{field_name}_index
                    $indexId = "{$schema['name']}_{$fieldName}_index";
                    $url = "{$this->endpoint}/api/apps/{$this->appId}/indexes/{$indexId}";
                    $this->httpClient->delete($url);
                    $result['removed'][] = ['field' => $fieldName, 'type' => $existing['type']];
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to remove index on {$fieldName}: {$e->getMessage()}";
                    $result['success'] = false;
                }
            }
        }

        // Track unchanged indexes
        foreach ($existingIndexes as $fieldName => $existing) {
            if (isset($desiredIndexedFields[$fieldName])) {
                $result['unchanged'][] = ['field' => $fieldName, 'type' => $existing['type']];
            }
        }

        return $result;
    }

    // ========================================================================
    // Materialized Views Methods
    // ========================================================================

    /**
     * Create a new materialized view
     *
     * @param string $name Unique name for the view
     * @param list<string> $sourceCollections Collections this view depends on
     * @param array<string, mixed> $query Query definition for the view
     * @return MaterializedView
     */
    public function createView(string $name, array $sourceCollections, array $query): array
    {
        $payload = [
            'name' => $name,
            'source_collections' => $sourceCollections,
            'query' => $query,
        ];

        $url = "{$this->endpoint}/apps/{$this->appId}/views";
        return $this->httpClient->post($url, $payload);
    }

    /**
     * List all materialized views for the app
     *
     * @return list<ViewInfo>
     */
    public function listViews(): array
    {
        $url = "{$this->endpoint}/apps/{$this->appId}/views";
        $response = $this->httpClient->get($url);
        return $response['views'] ?? $response;
    }

    /**
     * Get a specific materialized view by name
     *
     * @param string $name View name
     * @return MaterializedView
     */
    public function getView(string $name): array
    {
        $url = "{$this->endpoint}/apps/{$this->appId}/views/{$name}";
        return $this->httpClient->get($url);
    }

    /**
     * Delete a materialized view
     *
     * @param string $name View name
     * @return array{success: bool, message?: string}
     */
    public function deleteView(string $name): array
    {
        $url = "{$this->endpoint}/apps/{$this->appId}/views/{$name}";
        return $this->httpClient->delete($url);
    }

    /**
     * Refresh/rebuild a materialized view
     *
     * @param string $name View name
     * @return array{success: bool, message?: string}
     */
    public function refreshView(string $name): array
    {
        $url = "{$this->endpoint}/apps/{$this->appId}/views/{$name}/refresh";
        return $this->httpClient->post($url, []);
    }
}
