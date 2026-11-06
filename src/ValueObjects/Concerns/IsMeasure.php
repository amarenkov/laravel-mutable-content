<?php

namespace Amarenkov\MutableContent\ValueObjects\Concerns;

use InvalidArgumentException;

/**
 * A quantity kept in one base value: reading it from a field, arithmetic and comparison.
 * The class has the NAME and EPSILON constants and returns the base value from baseValue().
 */
trait IsMeasure
{
    // static
    /**
     * From a value in the base unit; a string must be numeric.
     */
    protected static function fromBaseValue(float|int|string $value): self
    {
        return new self(self::toNumber($value));
    }

    protected static function toNumber(float|int|string $value): float|int
    {
        if (!is_string($value)) {
            return $value;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(self::NAME.' must be numeric, "'.$value.'" given');
        }

        return (float)$value;
    }

    protected static function assertFinite(float $value): void
    {
        if (!is_finite($value)) {
            throw new InvalidArgumentException(self::NAME.' must be a finite number');
        }
    }

    /**
     * From a field value: null for null or an empty string, otherwise a number in the base unit.
     */
    public static function fromFieldValue(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new InvalidArgumentException(self::NAME.' must be numeric, '.get_debug_type($value).' given');
        }

        return self::fromBaseValue($value);
    }

    // protected
    abstract protected function baseValue(): float|int;

    // public
    public function multiply(float|int $factor): self
    {
        return self::fromBaseValue($this->baseValue() * $factor);
    }

    public function divide(float|int $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException(self::NAME.' cannot be divided by zero');
        }

        return self::fromBaseValue($this->baseValue() / $divisor);
    }

    public function compareTo(self $other): int
    {
        if ($this->equals($other)) {
            return 0;
        }

        return $this->baseValue() <=> $other->baseValue();
    }

    public function equals(self $other): bool
    {
        return abs($this->baseValue() - $other->baseValue()) < self::EPSILON;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isZero(): bool
    {
        return abs($this->baseValue()) < self::EPSILON;
    }

    public function isNegative(): bool
    {
        return $this->baseValue() <= -self::EPSILON;
    }

    public function abs(): self
    {
        return self::fromBaseValue(abs($this->baseValue()));
    }

    public function toFieldValue(): float|int
    {
        return $this->baseValue();
    }

    public function jsonSerialize(): float|int
    {
        return $this->baseValue();
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
