<?php

namespace Amarenkov\MutableContent\ValueObjects;

use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\ValueObjects\Concerns\HasUnitScale;

/**
 * Duration in whole seconds.
 */
final readonly class Duration implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    use HasUnitScale;

    // const
    protected const NAME = 'Duration';

    /**
     * Whole seconds are equal when they do not differ.
     */
    public const EPSILON = 0.5;

    public const SECONDS_IN_MINUTE = 60;
    public const SECONDS_IN_HOUR = 3600;

    public const UNIT_SECOND = 's';
    public const UNIT_MINUTE = 'min';
    public const UNIT_HOUR = 'h';

    public const UNITS = [self::UNIT_SECOND, self::UNIT_MINUTE, self::UNIT_HOUR];

    protected const BASE_UNIT = self::UNIT_SECOND;

    protected const DEFAULT_FORMAT_UNIT = self::UNIT_HOUR;

    protected const UNIT_FACTORS = [
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
        return self::fromBaseValue($seconds);
    }

    /**
     * Rounded to whole seconds.
     */
    protected static function fromBaseValue(float|int|string $value): self
    {
        return new self((int)round(self::toNumber($value)));
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
        return self::fromBaseValue(self::toNumber($value) * self::unitFactor($unit));
    }

    // protected
    protected function baseValue(): int
    {
        return $this->seconds;
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
        return $this->seconds / self::unitFactor($unit);
    }

    /**
     * Hours, minutes and seconds as H:MM:SS, or H:MM without seconds; hours of two digits at least with $padHours.
     */
    public function clock(bool $withSeconds = true, bool $padHours = false): string
    {
        $seconds = abs($this->seconds);
        $sign = $this->seconds < 0 ? '-' : '';
        $hours = $padHours ? '%02d' : '%d';

        if (!$withSeconds) {
            return sprintf('%s'.$hours.':%02d', $sign, intdiv($seconds, self::SECONDS_IN_HOUR), intdiv($seconds % self::SECONDS_IN_HOUR, self::SECONDS_IN_MINUTE));
        }

        return sprintf('%s'.$hours.':%02d:%02d', $sign, intdiv($seconds, self::SECONDS_IN_HOUR), intdiv($seconds % self::SECONDS_IN_HOUR, self::SECONDS_IN_MINUTE), $seconds % self::SECONDS_IN_MINUTE);
    }

}
