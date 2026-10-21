<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\Helpers\NumberHelper;

/**
 * Duration in whole seconds.
 */
final readonly class Duration implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    // const
    public const SECONDS_IN_MINUTE = 60;
    public const SECONDS_IN_HOUR = 3600;

    public const UNIT_SECOND = 's';
    public const UNIT_MINUTE = 'min';
    public const UNIT_HOUR = 'h';

    public const UNITS = [self::UNIT_SECOND, self::UNIT_MINUTE, self::UNIT_HOUR];

    protected const UNIT_SECONDS = [
        self::UNIT_SECOND => 1,
        self::UNIT_MINUTE => self::SECONDS_IN_MINUTE,
        self::UNIT_HOUR => self::SECONDS_IN_HOUR,
    ];

    protected const UNIT_FORMAT_DECIMALS = [
        self::UNIT_SECOND => 0,
        self::UNIT_MINUTE => 2,
        self::UNIT_HOUR => 2,
    ];

    // static
    public static function fromSeconds(float|int|string $seconds): self
    {
        if (is_string($seconds)) {
            if (!is_numeric($seconds)) {
                throw new InvalidArgumentException('Duration must be numeric, "'.$seconds.'" given');
            }

            $seconds = (float)$seconds;
        }

        return new self((int)round($seconds));
    }

    public static function fromMinutes(float|int $minutes): self
    {
        return self::fromSeconds($minutes * self::SECONDS_IN_MINUTE);
    }

    public static function fromHours(float|int $hours): self
    {
        return self::fromSeconds($hours * self::SECONDS_IN_HOUR);
    }

    public static function fromUnit(float|int|string $value, string $unit): self
    {
        if (is_string($value)) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException('Duration must be numeric, "'.$value.'" given');
            }

            $value = (float)$value;
        }

        return self::fromSeconds($value * self::unitSeconds($unit));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * From a field value: null for null or an empty string, otherwise as fromSeconds().
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
            throw new InvalidArgumentException('Duration must be numeric, '.get_debug_type($value).' given');
        }

        return self::fromSeconds($value);
    }

    /**
     * @param iterable<self> $durations
     */
    public static function sum(iterable $durations): self
    {
        $seconds = 0;

        foreach ($durations as $duration) {
            $seconds += $duration->seconds;
        }

        return new self($seconds);
    }

    public static function units(): array
    {
        return self::UNITS;
    }

    public static function baseUnit(): string
    {
        return self::UNIT_SECOND;
    }

    public static function unitLabel(string $unit): string
    {
        self::unitSeconds($unit);

        return __('mutable-content::units.'.$unit);
    }

    /**
     * @return array<string, string> unit => label
     */
    public static function unitOptions(): array
    {
        return array_combine(self::UNITS, array_map(fn ($unit) => self::unitLabel($unit), self::UNITS));
    }

    protected static function unitSeconds(string $unit): int
    {
        if (!isset(self::UNIT_SECONDS[$unit])) {
            throw new InvalidArgumentException('Unknown duration unit "'.$unit.'"');
        }

        return self::UNIT_SECONDS[$unit];
    }

    // public
    public function __construct(
        public int $seconds
    ) {
    }

    public function toSeconds(): int
    {
        return $this->seconds;
    }

    public function toMinutes(): float
    {
        return $this->seconds / self::SECONDS_IN_MINUTE;
    }

    public function toHours(): float
    {
        return $this->seconds / self::SECONDS_IN_HOUR;
    }

    public function toUnit(string $unit): float
    {
        return $this->seconds / self::unitSeconds($unit);
    }

    public function add(self $other): self
    {
        return new self($this->seconds + $other->seconds);
    }

    public function subtract(self $other): self
    {
        return new self($this->seconds - $other->seconds);
    }

    public function multiply(float|int $factor): self
    {
        return self::fromSeconds($this->seconds * $factor);
    }

    public function divide(float|int $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Duration cannot be divided by zero');
        }

        return self::fromSeconds($this->seconds / $divisor);
    }

    public function compareTo(self $other): int
    {
        return $this->seconds <=> $other->seconds;
    }

    public function equals(self $other): bool
    {
        return $this->seconds === $other->seconds;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->seconds > $other->seconds;
    }

    public function isLessThan(self $other): bool
    {
        return $this->seconds < $other->seconds;
    }

    public function isZero(): bool
    {
        return $this->seconds === 0;
    }

    public function isNegative(): bool
    {
        return $this->seconds < 0;
    }

    public function abs(): self
    {
        return new self(abs($this->seconds));
    }

    /**
     * Hours, minutes and seconds as H:MM:SS, or H:MM without seconds.
     */
    public function clock(bool $withSeconds = true): string
    {
        $seconds = abs($this->seconds);
        $sign = $this->seconds < 0 ? '-' : '';

        if (!$withSeconds) {
            return sprintf('%s%d:%02d', $sign, intdiv($seconds, self::SECONDS_IN_HOUR), intdiv($seconds % self::SECONDS_IN_HOUR, self::SECONDS_IN_MINUTE));
        }

        return sprintf('%s%d:%02d:%02d', $sign, intdiv($seconds, self::SECONDS_IN_HOUR), intdiv($seconds % self::SECONDS_IN_HOUR, self::SECONDS_IN_MINUTE), $seconds % self::SECONDS_IN_MINUTE);
    }

    /**
     * Format in hours by default, to two decimals without trailing zeros.
     */
    public function format(?int $decimals = null, ?string $decimalSeparator = null, ?string $thousandsSeparator = null, ?string $unit = null): string
    {
        $unit ??= self::UNIT_HOUR;
        $value = $this->toUnit($unit);
        $decimals ??= self::UNIT_FORMAT_DECIMALS[$unit];

        $formatted = NumberHelper::format($value, $decimals, $decimalSeparator, $thousandsSeparator);

        return $formatted.' '.self::unitLabel($unit);
    }

    public function toFieldValue(): int
    {
        return $this->seconds;
    }

    public function jsonSerialize(): int
    {
        return $this->seconds;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
