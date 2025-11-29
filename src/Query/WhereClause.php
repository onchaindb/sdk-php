<?php

declare(strict_types=1);

namespace OnChainDB\Query;

/**
 * Fluent where clause builder with all operators
 */
class WhereClause
{
    private QueryBuilder $queryBuilder;
    private string $fieldName;

    public function __construct(QueryBuilder $queryBuilder, string $fieldName)
    {
        $this->queryBuilder = $queryBuilder;
        $this->fieldName = $fieldName;
    }

    // ===== COMPARISON OPERATORS =====

    public function equals(mixed $value): QueryBuilder
    {
        return $this->setCondition(['is' => $value]);
    }

    public function notEquals(mixed $value): QueryBuilder
    {
        return $this->setCondition(['isNot' => $value]);
    }

    public function greaterThan(int|float $value): QueryBuilder
    {
        return $this->setCondition(['greaterThan' => $value]);
    }

    public function greaterThanOrEqual(int|float $value): QueryBuilder
    {
        return $this->setCondition(['greaterThanOrEqual' => $value]);
    }

    public function lessThan(int|float $value): QueryBuilder
    {
        return $this->setCondition(['lessThan' => $value]);
    }

    public function lessThanOrEqual(int|float $value): QueryBuilder
    {
        return $this->setCondition(['lessThanOrEqual' => $value]);
    }

    public function between(int|float $min, int|float $max): QueryBuilder
    {
        return $this->setCondition(['betweenOp' => ['from' => $min, 'to' => $max]]);
    }

    // ===== STRING OPERATORS =====

    public function contains(string $value): QueryBuilder
    {
        return $this->setCondition(['includes' => $value]);
    }

    public function startsWith(string $value): QueryBuilder
    {
        return $this->setCondition(['startsWith' => $value]);
    }

    public function endsWith(string $value): QueryBuilder
    {
        return $this->setCondition(['endsWith' => $value]);
    }

    public function regExpMatches(string $pattern): QueryBuilder
    {
        return $this->setCondition(['regExpMatches' => $pattern]);
    }

    public function includesCaseInsensitive(string $value): QueryBuilder
    {
        return $this->setCondition(['includesCaseInsensitive' => $value]);
    }

    public function startsWithCaseInsensitive(string $value): QueryBuilder
    {
        return $this->setCondition(['startsWithCaseInsensitive' => $value]);
    }

    public function endsWithCaseInsensitive(string $value): QueryBuilder
    {
        return $this->setCondition(['endsWithCaseInsensitive' => $value]);
    }

    // ===== ARRAY OPERATORS =====

    /**
     * @param array<int, mixed> $values
     */
    public function in(array $values): QueryBuilder
    {
        return $this->setCondition(['in' => $values]);
    }

    /**
     * @param array<int, mixed> $values
     */
    public function notIn(array $values): QueryBuilder
    {
        return $this->setCondition(['notIn' => $values]);
    }

    // ===== EXISTENCE OPERATORS =====

    public function exists(): QueryBuilder
    {
        return $this->setCondition(['exists' => true]);
    }

    public function notExists(): QueryBuilder
    {
        return $this->setCondition(['exists' => false]);
    }

    public function isNull(): QueryBuilder
    {
        return $this->setCondition(['isNull' => true]);
    }

    public function isNotNull(): QueryBuilder
    {
        return $this->setCondition(['isNull' => false]);
    }

    // ===== BOOLEAN OPERATORS =====

    public function isTrue(): QueryBuilder
    {
        return $this->equals(true);
    }

    public function isFalse(): QueryBuilder
    {
        return $this->equals(false);
    }

    // ===== IP OPERATORS =====

    public function isLocalIp(): QueryBuilder
    {
        return $this->setCondition(['isLocalIp' => true]);
    }

    public function isExternalIp(): QueryBuilder
    {
        return $this->setCondition(['isExternalIp' => true]);
    }

    public function inCountry(string $countryCode): QueryBuilder
    {
        return $this->setCondition(['inCountry' => $countryCode]);
    }

    public function cidr(string $cidrRange): QueryBuilder
    {
        return $this->setCondition(['cidr' => $cidrRange]);
    }

    // ===== SPECIAL OPERATORS =====

    public function b64(string $value): QueryBuilder
    {
        return $this->setCondition(['b64' => $value]);
    }

    public function inDataset(string $dataset): QueryBuilder
    {
        return $this->setCondition(['inDataset' => $dataset]);
    }

    /**
     * Build nested field condition for dot notation
     *
     * @return array<string, mixed>
     */
    private function buildNestedCondition(array $condition): array
    {
        $parts = explode('.', $this->fieldName);

        if (count($parts) === 1) {
            return [$this->fieldName => $condition];
        }

        // Build nested structure from inside out
        $result = $condition;
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            $result = [$parts[$i] => $result];
        }

        return $result;
    }

    private function setCondition(array $condition): QueryBuilder
    {
        $nestedCondition = $this->buildNestedCondition($condition);
        $this->queryBuilder->setFindConditions($nestedCondition);
        return $this->queryBuilder;
    }
}
