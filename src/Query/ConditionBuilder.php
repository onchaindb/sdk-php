<?php

declare(strict_types=1);

namespace OnChainDB\Query;

/**
 * Builder for creating query conditions
 */
class ConditionBuilder
{
    /**
     * Create a field condition
     */
    public function field(string $fieldName): FieldCondition
    {
        return new FieldCondition($fieldName);
    }

    /**
     * Create an AND group
     *
     * @param callable(): array<int, LogicalOperator> $fn
     */
    public function andGroup(callable $fn): LogicalOperator
    {
        return LogicalOperator::And($fn());
    }

    /**
     * Create an OR group
     *
     * @param callable(): array<int, LogicalOperator> $fn
     */
    public function orGroup(callable $fn): LogicalOperator
    {
        return LogicalOperator::Or($fn());
    }

    /**
     * Create a NOT group
     *
     * @param callable(): array<int, LogicalOperator> $fn
     */
    public function notGroup(callable $fn): LogicalOperator
    {
        return LogicalOperator::Not($fn());
    }
}
