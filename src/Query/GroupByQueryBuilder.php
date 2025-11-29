<?php

declare(strict_types=1);

namespace OnChainDB\Query;

/**
 * Builder for grouped aggregation queries
 */
class GroupByQueryBuilder
{
    private QueryBuilder $queryBuilder;
    private string $groupByField;

    public function __construct(QueryBuilder $queryBuilder, string $groupByField)
    {
        $this->queryBuilder = $queryBuilder;
        $this->groupByField = $groupByField;
    }

    /**
     * Count records in each group
     *
     * @return array<string, int>
     */
    public function count(): array
    {
        $response = $this->queryBuilder->clone()->execute();
        $records = $response['records'] ?? [];

        $groups = [];
        foreach ($records as $record) {
            $key = $this->getGroupKey($record);
            $groups[$key] = ($groups[$key] ?? 0) + 1;
        }

        return $groups;
    }

    /**
     * Sum a numeric field within each group
     *
     * @return array<string, float>
     */
    public function sumBy(string $field): array
    {
        $response = $this->queryBuilder->clone()->execute();
        $records = $response['records'] ?? [];

        $groups = [];
        foreach ($records as $record) {
            $key = $this->getGroupKey($record);
            $value = $record[$field] ?? 0;
            $groups[$key] = ($groups[$key] ?? 0) + (is_numeric($value) ? (float)$value : 0);
        }

        return $groups;
    }

    /**
     * Calculate average of a numeric field within each group
     *
     * @return array<string, float>
     */
    public function avgBy(string $field): array
    {
        $response = $this->queryBuilder->clone()->execute();
        $records = $response['records'] ?? [];

        $groups = [];
        foreach ($records as $record) {
            $key = $this->getGroupKey($record);
            if (!isset($groups[$key])) {
                $groups[$key] = ['sum' => 0.0, 'count' => 0];
            }
            $value = $record[$field] ?? 0;
            $groups[$key]['sum'] += is_numeric($value) ? (float)$value : 0;
            $groups[$key]['count']++;
        }

        $result = [];
        foreach ($groups as $key => $data) {
            $result[$key] = $data['count'] > 0 ? $data['sum'] / $data['count'] : 0.0;
        }

        return $result;
    }

    /**
     * Find maximum value of a field within each group
     *
     * @return array<string, mixed>
     */
    public function maxBy(string $field): array
    {
        $response = $this->queryBuilder->clone()->execute();
        $records = $response['records'] ?? [];

        $groups = [];
        foreach ($records as $record) {
            $key = $this->getGroupKey($record);
            $value = $record[$field] ?? null;
            if (!isset($groups[$key]) || $value > $groups[$key]) {
                $groups[$key] = $value;
            }
        }

        return $groups;
    }

    /**
     * Find minimum value of a field within each group
     *
     * @return array<string, mixed>
     */
    public function minBy(string $field): array
    {
        $response = $this->queryBuilder->clone()->execute();
        $records = $response['records'] ?? [];

        $groups = [];
        foreach ($records as $record) {
            $key = $this->getGroupKey($record);
            $value = $record[$field] ?? null;
            if (!isset($groups[$key]) || $value < $groups[$key]) {
                $groups[$key] = $value;
            }
        }

        return $groups;
    }

    /**
     * Get the group key from a record, supporting nested field paths
     */
    private function getGroupKey(array $record): string
    {
        // Support nested field paths (e.g., "user.country")
        if (str_contains($this->groupByField, '.')) {
            $parts = explode('.', $this->groupByField);
            $current = $record;
            foreach ($parts as $part) {
                $current = $current[$part] ?? null;
            }
            return (string)($current ?? 'null');
        }

        return (string)($record[$this->groupByField] ?? 'null');
    }
}
