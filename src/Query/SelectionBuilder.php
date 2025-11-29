<?php

declare(strict_types=1);

namespace OnChainDB\Query;

/**
 * Builder for constructing field selections
 */
class SelectionBuilder
{
    /** @var array<string, mixed> */
    private array $selections = [];

    /**
     * Include a field in the selection
     */
    public function field(string $name): self
    {
        $this->selections[$name] = true;
        return $this;
    }

    /**
     * Include multiple fields
     *
     * @param array<int, string> $names
     */
    public function fields(array $names): self
    {
        foreach ($names as $name) {
            $this->selections[$name] = true;
        }
        return $this;
    }

    /**
     * Configure nested field selection
     *
     * @param callable(SelectionBuilder): SelectionBuilder $builderFn
     */
    public function nested(string $name, callable $builderFn): self
    {
        $nestedBuilder = new self();
        $builderFn($nestedBuilder);
        $this->selections[$name] = $nestedBuilder->build();
        return $this;
    }

    /**
     * Exclude a field from selection
     */
    public function exclude(string $name): self
    {
        $this->selections[$name] = false;
        return $this;
    }

    /**
     * Exclude multiple fields
     *
     * @param array<int, string> $names
     */
    public function excludeFields(array $names): self
    {
        foreach ($names as $name) {
            $this->selections[$name] = false;
        }
        return $this;
    }

    /**
     * Clear all selections
     */
    public function clear(): self
    {
        $this->selections = [];
        return $this;
    }

    /**
     * Build the selection map
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return $this->selections;
    }

    /**
     * Create a selection that includes all fields
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [];
    }
}
