<?php

declare(strict_types=1);

namespace OnChainDB\Query;

/**
 * Logical operator for building complex query conditions
 */
class LogicalOperator
{
    private string $type;
    /** @var array<int, LogicalOperator|array<string, mixed>> */
    private array $conditions;

    private function __construct(string $type, array $conditions)
    {
        $this->type = $type;
        $this->conditions = $conditions;
    }

    /**
     * Create an AND condition
     *
     * @param array<int, LogicalOperator> $conditions
     */
    public static function And(array $conditions): self
    {
        return new self('and', $conditions);
    }

    /**
     * Create an OR condition
     *
     * @param array<int, LogicalOperator> $conditions
     */
    public static function Or(array $conditions): self
    {
        return new self('or', $conditions);
    }

    /**
     * Create a NOT condition
     *
     * @param array<int, LogicalOperator> $conditions
     */
    public static function Not(array $conditions): self
    {
        return new self('not', $conditions);
    }

    /**
     * Create a field condition
     */
    public static function Condition(FieldCondition $condition): self
    {
        return new self('condition', [$condition->toArray()]);
    }

    /**
     * Convert to array representation for JSON serialization
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->type === 'condition') {
            return $this->conditions[0];
        }

        $result = [];
        foreach ($this->conditions as $condition) {
            if ($condition instanceof LogicalOperator) {
                $result[] = $condition->toArray();
            } else {
                $result[] = $condition;
            }
        }

        return [$this->type => $result];
    }
}
