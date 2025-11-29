<?php

declare(strict_types=1);

namespace OnChainDB\Query;

/**
 * Fluent builder for constructing server-side JOINs
 */
class JoinBuilder
{
    private QueryBuilder $parentBuilder;
    private string $alias;
    private string $model;
    private ?bool $many;
    /** @var array<string, mixed> */
    private array $findConditions = [];
    /** @var array<string, bool>|null */
    private ?array $selections = null;
    /** @var array<int, array<string, mixed>> */
    private array $nestedJoins = [];

    public function __construct(
        QueryBuilder $parentBuilder,
        string $alias,
        string $model,
        ?bool $many = null
    ) {
        $this->parentBuilder = $parentBuilder;
        $this->alias = $alias;
        $this->model = $model;
        $this->many = $many;
    }

    /**
     * Add a simple equality condition
     */
    public function onField(string $fieldName): JoinWhereClause
    {
        return new JoinWhereClause($this, $fieldName);
    }

    /**
     * Add complex filter conditions
     *
     * @param callable(ConditionBuilder): LogicalOperator $builderFn
     */
    public function on(callable $builderFn): self
    {
        $builder = new ConditionBuilder();
        $operator = $builderFn($builder);
        $this->findConditions = $operator->toArray();
        return $this;
    }

    /**
     * Select specific fields from joined collection
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
     * Select all fields from joined collection
     */
    public function selectAll(): self
    {
        $this->selections = [];
        return $this;
    }

    /**
     * Configure selection with a builder
     *
     * @param callable(SelectionBuilder): SelectionBuilder $builderFn
     */
    public function selecting(callable $builderFn): self
    {
        $builder = new SelectionBuilder();
        $builderFn($builder);
        $this->selections = $builder->build();
        return $this;
    }

    /**
     * Add a nested one-to-one JOIN
     */
    public function joinOne(string $alias, string $model): NestedJoinBuilder
    {
        return new NestedJoinBuilder($this, $alias, $model, false);
    }

    /**
     * Add a nested one-to-many JOIN
     */
    public function joinMany(string $alias, string $model): NestedJoinBuilder
    {
        return new NestedJoinBuilder($this, $alias, $model, true);
    }

    /**
     * Add a nested join config
     * @internal
     */
    public function addNestedJoin(array $config): void
    {
        $this->nestedJoins[] = $config;
    }

    /**
     * Set find conditions directly
     * @internal
     */
    public function setFindConditions(array $conditions): void
    {
        $this->findConditions = $conditions;
    }

    /**
     * Complete the JOIN and return to the parent builder
     */
    public function build(): QueryBuilder
    {
        $find = $this->findConditions;

        // Add nested JOINs to find conditions
        foreach ($this->nestedJoins as $nestedJoin) {
            $joinConfig = [
                'resolve' => $nestedJoin['resolve'],
                'model' => $nestedJoin['model'],
            ];

            if (isset($nestedJoin['many'])) {
                $joinConfig['many'] = $nestedJoin['many'];
            }

            $find[$nestedJoin['alias']] = $joinConfig;
        }

        $config = [
            'alias' => $this->alias,
            'model' => $this->model,
            'resolve' => [
                'find' => !empty($find) ? $find : null,
                'select' => $this->selections ?? [],
            ],
        ];

        if ($this->many !== null) {
            $config['many'] = $this->many;
        }

        $this->parentBuilder->addServerJoin($config);

        return $this->parentBuilder;
    }
}

/**
 * Where clause for JOIN conditions
 */
class JoinWhereClause
{
    private JoinBuilder $joinBuilder;
    private string $fieldName;

    public function __construct(JoinBuilder $joinBuilder, string $fieldName)
    {
        $this->joinBuilder = $joinBuilder;
        $this->fieldName = $fieldName;
    }

    public function equals(mixed $value): JoinBuilder
    {
        $this->setCondition(['is' => $value]);
        return $this->joinBuilder;
    }

    /**
     * @param array<int, mixed> $values
     */
    public function in(array $values): JoinBuilder
    {
        $this->setCondition(['in' => $values]);
        return $this->joinBuilder;
    }

    public function greaterThan(int|float $value): JoinBuilder
    {
        $this->setCondition(['greaterThan' => $value]);
        return $this->joinBuilder;
    }

    public function lessThan(int|float $value): JoinBuilder
    {
        $this->setCondition(['lessThan' => $value]);
        return $this->joinBuilder;
    }

    public function isNull(): JoinBuilder
    {
        $this->setCondition(['isNull' => true]);
        return $this->joinBuilder;
    }

    public function isNotNull(): JoinBuilder
    {
        $this->setCondition(['isNull' => false]);
        return $this->joinBuilder;
    }

    private function setCondition(array $condition): void
    {
        $this->joinBuilder->setFindConditions([$this->fieldName => $condition]);
    }
}

/**
 * Nested join builder for joins within joins
 */
class NestedJoinBuilder
{
    private JoinBuilder $parentBuilder;
    private string $alias;
    private string $model;
    private ?bool $many;
    /** @var array<string, mixed> */
    private array $findConditions = [];
    /** @var array<string, bool>|null */
    private ?array $selections = null;

    public function __construct(
        JoinBuilder $parentBuilder,
        string $alias,
        string $model,
        ?bool $many = null
    ) {
        $this->parentBuilder = $parentBuilder;
        $this->alias = $alias;
        $this->model = $model;
        $this->many = $many;
    }

    public function onField(string $fieldName): NestedJoinWhereClause
    {
        return new NestedJoinWhereClause($this, $fieldName);
    }

    /**
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

    public function selectAll(): self
    {
        $this->selections = [];
        return $this;
    }

    /**
     * @internal
     */
    public function setFindConditions(array $conditions): void
    {
        $this->findConditions = $conditions;
    }

    public function build(): JoinBuilder
    {
        $config = [
            'alias' => $this->alias,
            'model' => $this->model,
            'resolve' => [
                'find' => !empty($this->findConditions) ? $this->findConditions : null,
                'select' => $this->selections ?? [],
            ],
        ];

        if ($this->many !== null) {
            $config['many'] = $this->many;
        }

        $this->parentBuilder->addNestedJoin($config);

        return $this->parentBuilder;
    }
}

/**
 * Where clause for nested JOIN conditions
 */
class NestedJoinWhereClause
{
    private NestedJoinBuilder $joinBuilder;
    private string $fieldName;

    public function __construct(NestedJoinBuilder $joinBuilder, string $fieldName)
    {
        $this->joinBuilder = $joinBuilder;
        $this->fieldName = $fieldName;
    }

    public function equals(mixed $value): NestedJoinBuilder
    {
        $this->joinBuilder->setFindConditions([$this->fieldName => ['is' => $value]]);
        return $this->joinBuilder;
    }
}
