<?php

declare(strict_types=1);

namespace OnChainDB\Query;

use OnChainDB\Http\HttpClientInterface;
use OnChainDB\Exception\OnChainDBException;

/**
 * Fluent query builder for constructing OnChainDB queries
 */
class QueryBuilder
{
    private ?HttpClientInterface $httpClient;
    private ?string $serverUrl;
    private ?string $app;
    private ?string $collectionName = null;
    private ?array $findConditions = null;
    private ?array $selections = null;
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private ?string $sortBy = null;
    private ?string $sortDirection = null;
    private ?bool $includeHistoryValue = null;
    /** @var array<int, array<string, mixed>> */
    private array $serverJoinConfigs = [];

    public function __construct(
        ?HttpClientInterface $httpClient = null,
        ?string $serverUrl = null,
        ?string $app = null
    ) {
        $this->httpClient = $httpClient;
        $this->serverUrl = $serverUrl;
        $this->app = $app;
    }

    /**
     * Set the target collection
     */
    public function collection(string $name): self
    {
        $this->collectionName = $name;
        return $this;
    }

    /**
     * Add a where field condition
     */
    public function whereField(string $fieldName): WhereClause
    {
        return new WhereClause($this, $fieldName);
    }

    /**
     * Set complex find conditions using a builder function
     *
     * @param callable(ConditionBuilder): LogicalOperator $builderFn
     */
    public function find(callable $builderFn): self
    {
        $builder = new ConditionBuilder();
        $operator = $builderFn($builder);
        $this->findConditions = $operator->toArray();
        return $this;
    }

    /**
     * Select specific fields
     *
     * @param array<int, string> $fields
     */
    public function selectFields(array $fields): self
    {
        $this->selections = [];
        foreach ($fields as $field) {
            $this->selections[$field] = true;
        }
        return $this;
    }

    /**
     * Select all fields
     */
    public function selectAll(): self
    {
        $this->selections = [];
        return $this;
    }

    /**
     * Configure selection with a builder function
     *
     * @param callable(SelectionBuilder): SelectionBuilder $builderFn
     */
    public function select(callable $builderFn): self
    {
        $builder = new SelectionBuilder();
        $builderFn($builder);
        $this->selections = $builder->build();
        return $this;
    }

    /**
     * Limit the number of results
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    /**
     * Skip a number of results (pagination)
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }

    /**
     * Sort results by a field
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->sortBy = $field;
        $this->sortDirection = $direction;
        return $this;
    }

    /**
     * Include historical versions of records
     */
    public function includeHistory(bool $include = true): self
    {
        $this->includeHistoryValue = $include;
        return $this;
    }

    /**
     * Add a one-to-one JOIN
     */
    public function joinOne(string $alias, string $model): JoinBuilder
    {
        return new JoinBuilder($this, $alias, $model, false);
    }

    /**
     * Add a one-to-many JOIN
     */
    public function joinMany(string $alias, string $model): JoinBuilder
    {
        return new JoinBuilder($this, $alias, $model, true);
    }

    /**
     * Add a JOIN with default behavior (returns array)
     */
    public function joinWith(string $alias, string $model): JoinBuilder
    {
        return new JoinBuilder($this, $alias, $model);
    }

    /**
     * Internal method to add a server join config
     * @internal
     */
    public function addServerJoin(array $config): void
    {
        $this->serverJoinConfigs[] = $config;
    }

    /**
     * Execute the query
     *
     * @return array<string, mixed>
     * @throws OnChainDBException
     */
    public function execute(): array
    {
        if ($this->httpClient === null) {
            throw new OnChainDBException('HTTP client is required for query execution');
        }
        if ($this->serverUrl === null) {
            throw new OnChainDBException('Server URL is required for query execution');
        }

        $request = $this->getQueryRequest();

        // Log the query request
        error_log("[OnChainDB] Query request: " . json_encode($request, JSON_PRETTY_PRINT));

        try {
            $url = "{$this->serverUrl}/list";
            error_log("[OnChainDB] POST {$url}");

            $response = $this->httpClient->post($url, $request);

            error_log("[OnChainDB] Response: " . json_encode($response, JSON_PRETTY_PRINT));
            return $response;
        } catch (\Exception $e) {
            error_log("[OnChainDB] Query failed: " . $e->getMessage());
            throw new OnChainDBException('Query execution failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute the query and return the latest record by metadata
     *
     * @return array<string, mixed>|null
     */
    public function executeUnique(): ?array
    {
        $response = $this->execute();
        $records = $response['records'] ?? [];

        if (empty($records)) {
            return null;
        }

        // Sort by metadata timestamp (updatedAt first, then createdAt) descending
        usort($records, function ($a, $b) {
            $getTimestamp = function ($record): ?string {
                return $record['updatedAt']
                    ?? $record['updated_at']
                    ?? $record['createdAt']
                    ?? $record['created_at']
                    ?? null;
            };

            $tsA = $getTimestamp($a);
            $tsB = $getTimestamp($b);

            if ($tsB !== null && $tsA !== null) {
                return strcmp($tsB, $tsA);
            }
            if ($tsB !== null) {
                return -1;
            }
            if ($tsA !== null) {
                return 1;
            }
            return 0;
        });

        return $records[0];
    }

    // ===== AGGREGATION METHODS =====

    /**
     * Count matching records
     */
    public function count(): int
    {
        $response = $this->execute();
        return count($response['records'] ?? []);
    }

    /**
     * Sum values of a numeric field
     */
    public function sumBy(string $field): float
    {
        $response = $this->execute();
        $records = $response['records'] ?? [];

        return array_reduce($records, function (float $sum, array $record) use ($field) {
            $value = $record[$field] ?? 0;
            return $sum + (is_numeric($value) ? (float)$value : 0);
        }, 0.0);
    }

    /**
     * Calculate average of a numeric field
     */
    public function avgBy(string $field): float
    {
        $response = $this->execute();
        $records = $response['records'] ?? [];

        if (empty($records)) {
            return 0.0;
        }

        $sum = $this->sumBy($field);
        return $sum / count($records);
    }

    /**
     * Find maximum value of a field
     *
     * @return mixed|null
     */
    public function maxBy(string $field): mixed
    {
        $response = $this->execute();
        $records = $response['records'] ?? [];

        if (empty($records)) {
            return null;
        }

        $max = null;
        foreach ($records as $record) {
            $value = $record[$field] ?? null;
            if ($max === null || $value > $max) {
                $max = $value;
            }
        }

        return $max;
    }

    /**
     * Find minimum value of a field
     *
     * @return mixed|null
     */
    public function minBy(string $field): mixed
    {
        $response = $this->execute();
        $records = $response['records'] ?? [];

        if (empty($records)) {
            return null;
        }

        $min = null;
        foreach ($records as $record) {
            $value = $record[$field] ?? null;
            if ($min === null || $value < $min) {
                $min = $value;
            }
        }

        return $min;
    }

    /**
     * Get distinct values of a field
     *
     * @return array<int, mixed>
     */
    public function distinctBy(string $field): array
    {
        $response = $this->execute();
        $records = $response['records'] ?? [];

        $unique = [];
        foreach ($records as $record) {
            $value = $record[$field] ?? null;
            if ($value !== null && !in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }

        return $unique;
    }

    /**
     * Count distinct values of a field
     */
    public function countDistinct(string $field): int
    {
        return count($this->distinctBy($field));
    }

    /**
     * Start a grouped aggregation
     */
    public function groupBy(string $field): GroupByQueryBuilder
    {
        return new GroupByQueryBuilder($this, $field);
    }

    /**
     * Get the raw query request object
     *
     * @return array<string, mixed>
     */
    public function getQueryRequest(): array
    {
        $queryValue = $this->buildQueryValue();

        $request = array_merge($queryValue, [
            'root' => "{$this->app}::{$this->collectionName}",
        ]);

        if ($this->limitValue !== null) {
            $request['limit'] = $this->limitValue;
        }
        if ($this->offsetValue !== null) {
            $request['offset'] = $this->offsetValue;
        }
        if ($this->sortBy !== null) {
            $request['sortBy'] = $this->sortBy;
        }

        return $request;
    }

    /**
     * Build raw query for debugging
     *
     * @return array<string, mixed>
     */
    public function buildRawQuery(): array
    {
        return $this->getQueryRequest();
    }

    /**
     * Check if the query is valid
     */
    public function isValid(): bool
    {
        return $this->findConditions !== null || $this->selections !== null;
    }

    /**
     * Clone the query builder
     */
    public function clone(): self
    {
        $cloned = new self($this->httpClient, $this->serverUrl, $this->app);
        $cloned->collectionName = $this->collectionName;
        $cloned->findConditions = $this->findConditions;
        $cloned->selections = $this->selections;
        $cloned->limitValue = $this->limitValue;
        $cloned->offsetValue = $this->offsetValue;
        $cloned->sortBy = $this->sortBy;
        $cloned->sortDirection = $this->sortDirection;
        $cloned->includeHistoryValue = $this->includeHistoryValue;
        $cloned->serverJoinConfigs = $this->serverJoinConfigs;
        return $cloned;
    }

    /**
     * Set find conditions directly (used by WhereClause)
     * @internal
     */
    public function setFindConditions(array $conditions): void
    {
        $this->findConditions = $conditions;
    }

    /**
     * Build the query value object
     *
     * @return array<string, mixed>
     */
    private function buildQueryValue(): array
    {
        $find = $this->findConditions ?? [];
        $select = $this->selections ?? [];

        // Add server-side JOINs to the find conditions
        foreach ($this->serverJoinConfigs as $join) {
            $joinConfig = [
                'resolve' => $join['resolve'],
                'model' => $join['model'],
            ];

            if (isset($join['many'])) {
                $joinConfig['many'] = $join['many'];
            }

            $find[$join['alias']] = $joinConfig;
        }

        // Cast to object to ensure JSON encodes as {} not []
        $queryValue = [
            'find' => empty($find) ? (object)[] : $find,
            'select' => empty($select) ? (object)[] : $select,
        ];

        if ($this->includeHistoryValue !== null) {
            $queryValue['include_history'] = $this->includeHistoryValue;
        }

        return $queryValue;
    }
}
