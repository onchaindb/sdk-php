<?php

declare(strict_types=1);

namespace OnChainDB\Query;

/**
 * Field condition builder for creating individual field conditions
 */
class FieldCondition
{
    private string $fieldName;
    private string $operator;
    private mixed $value;

    public function __construct(string $fieldName)
    {
        $this->fieldName = $fieldName;
    }

    // ===== COMPARISON OPERATORS =====

    public function equals(mixed $value): self
    {
        $this->operator = 'is';
        $this->value = $value;
        return $this;
    }

    public function notEquals(mixed $value): self
    {
        $this->operator = 'isNot';
        $this->value = $value;
        return $this;
    }

    public function greaterThan(int|float $value): self
    {
        $this->operator = 'greaterThan';
        $this->value = $value;
        return $this;
    }

    public function greaterThanOrEqual(int|float $value): self
    {
        $this->operator = 'greaterThanOrEqual';
        $this->value = $value;
        return $this;
    }

    public function lessThan(int|float $value): self
    {
        $this->operator = 'lessThan';
        $this->value = $value;
        return $this;
    }

    public function lessThanOrEqual(int|float $value): self
    {
        $this->operator = 'lessThanOrEqual';
        $this->value = $value;
        return $this;
    }

    public function between(int|float $min, int|float $max): self
    {
        $this->operator = 'betweenOp';
        $this->value = ['from' => $min, 'to' => $max];
        return $this;
    }

    // ===== STRING OPERATORS =====

    public function contains(string $value): self
    {
        $this->operator = 'includes';
        $this->value = $value;
        return $this;
    }

    public function startsWith(string $value): self
    {
        $this->operator = 'startsWith';
        $this->value = $value;
        return $this;
    }

    public function endsWith(string $value): self
    {
        $this->operator = 'endsWith';
        $this->value = $value;
        return $this;
    }

    public function regExpMatches(string $pattern): self
    {
        $this->operator = 'regExpMatches';
        $this->value = $pattern;
        return $this;
    }

    public function includesCaseInsensitive(string $value): self
    {
        $this->operator = 'includesCaseInsensitive';
        $this->value = $value;
        return $this;
    }

    public function startsWithCaseInsensitive(string $value): self
    {
        $this->operator = 'startsWithCaseInsensitive';
        $this->value = $value;
        return $this;
    }

    public function endsWithCaseInsensitive(string $value): self
    {
        $this->operator = 'endsWithCaseInsensitive';
        $this->value = $value;
        return $this;
    }

    // ===== ARRAY OPERATORS =====

    /**
     * @param array<int, mixed> $values
     */
    public function in(array $values): self
    {
        $this->operator = 'in';
        $this->value = $values;
        return $this;
    }

    /**
     * @param array<int, mixed> $values
     */
    public function notIn(array $values): self
    {
        $this->operator = 'notIn';
        $this->value = $values;
        return $this;
    }

    // ===== EXISTENCE OPERATORS =====

    public function exists(): self
    {
        $this->operator = 'exists';
        $this->value = true;
        return $this;
    }

    public function notExists(): self
    {
        $this->operator = 'exists';
        $this->value = false;
        return $this;
    }

    public function isNull(): self
    {
        $this->operator = 'isNull';
        $this->value = true;
        return $this;
    }

    public function isNotNull(): self
    {
        $this->operator = 'isNull';
        $this->value = false;
        return $this;
    }

    // ===== BOOLEAN OPERATORS =====

    public function isTrue(): self
    {
        return $this->equals(true);
    }

    public function isFalse(): self
    {
        return $this->equals(false);
    }

    // ===== IP OPERATORS =====

    public function isLocalIp(): self
    {
        $this->operator = 'isLocalIp';
        $this->value = true;
        return $this;
    }

    public function isExternalIp(): self
    {
        $this->operator = 'isExternalIp';
        $this->value = true;
        return $this;
    }

    public function inCountry(string $countryCode): self
    {
        $this->operator = 'inCountry';
        $this->value = $countryCode;
        return $this;
    }

    public function cidr(string $cidrRange): self
    {
        $this->operator = 'cidr';
        $this->value = $cidrRange;
        return $this;
    }

    // ===== SPECIAL OPERATORS =====

    public function b64(string $value): self
    {
        $this->operator = 'b64';
        $this->value = $value;
        return $this;
    }

    public function inDataset(string $dataset): self
    {
        $this->operator = 'inDataset';
        $this->value = $dataset;
        return $this;
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $condition = [$this->operator => $this->value];

        // Handle nested field names with dot notation
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
}
