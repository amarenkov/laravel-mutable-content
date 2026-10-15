<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

use Amarenkov\MutableContent\Helpers\NumberHelper;

use Amarenkov\MutableContent\Models\Lov\Currency;

/**
 * Amount of money in a currency of the currency LOV. Stored as {"amount": 1234.56, "currency": "USD"}:
 * the amount is a JSON number, exact in the database; in PHP it is kept as a decimal string, so the arithmetic
 * (brick/math) has no float errors.
 */
final readonly class Money implements FieldValue, JsonSerializable, Stringable
{
    // const
    public const KEY_AMOUNT = 'amount';
    public const KEY_CURRENCY = 'currency';

    /**
     * Digits after the point a float amount is read with: more than any currency has, fewer than a float keeps.
     */
    protected const FLOAT_DECIMALS = 8;

    /**
     * Most digits after the point a money field may keep (the decimals type setting).
     */
    public const MAX_DECIMALS = self::FLOAT_DECIMALS;

    protected const AMOUNT_PATTERN = '/^[-+]?(\d+(\.\d*)?|\.\d+)$/';

    // static
    public static function of(int|float|string|BigDecimal $amount, string $currency): self
    {
        return new self(self::toDecimalString($amount), $currency);
    }

    public static function zero(string $currency): self
    {
        return new self('0', $currency);
    }

    /**
     * Value from a field: {"amount": ..., "currency": ...}; null for an empty or a malformed value.
     */
    public static function fromFieldValue(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (!is_array($value) || !self::isAmount($value[self::KEY_AMOUNT] ?? null) || !is_string($value[self::KEY_CURRENCY] ?? null) || $value[self::KEY_CURRENCY] === '') {
            return null;
        }

        return self::of($value[self::KEY_AMOUNT], $value[self::KEY_CURRENCY]);
    }

    /**
     * An int, a finite float or a decimal string (a point as the separator, no exponent).
     */
    public static function isAmount(mixed $amount): bool
    {
        return is_int($amount)
            || (is_float($amount) && is_finite($amount))
            || (is_string($amount) && preg_match(self::AMOUNT_PATTERN, trim($amount)) === 1);
    }

    /**
     * Decimal string without the exponent, the plus sign and trailing zeros: "1234.5", "-3", "0".
     */
    public static function toDecimalString(int|float|string|BigDecimal $amount): string
    {
        if ($amount instanceof BigDecimal) {
            $amount = (string)$amount;
        } elseif (is_float($amount)) {
            if (!is_finite($amount)) {
                throw new InvalidArgumentException('Amount must be finite');
            }

            $amount = number_format($amount, self::FLOAT_DECIMALS, '.', '');
        } elseif (is_string($amount)) {
            $amount = trim($amount);

            if (preg_match(self::AMOUNT_PATTERN, $amount) !== 1) {
                throw new InvalidArgumentException('Amount must be a decimal number, "'.$amount.'" given');
            }
        }

        $amount = (string)$amount;

        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '+-');

        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        $integer = ltrim($integer, '0') ?: '0';
        $fraction = rtrim($fraction, '0');

        $result = $fraction === '' ? $integer : $integer.'.'.$fraction;

        return $negative && $result !== '0' ? '-'.$result : $result;
    }

    /**
     * Digits after the point of a decimal string.
     */
    public static function scaleOf(string $amount): int
    {
        $point = strpos($amount, '.');

        return $point === false ? 0 : strlen($amount) - $point - 1;
    }

    // protected
    protected function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('Currencies differ: '.$this->currency.' and '.$other->currency);
        }
    }

    protected function withAmount(BigDecimal $amount): self
    {
        return new self(self::toDecimalString($amount), $this->currency);
    }

    // public
    /**
     * Use of() to build from any amount: the constructor expects the decimal string of toDecimalString().
     */
    public function __construct(
        public string $amount,
        public string $currency
    )
    {
        if ($currency === '') {
            throw new InvalidArgumentException('Currency must not be empty');
        }
    }

    public function number(): BigDecimal
    {
        return BigDecimal::of($this->amount);
    }

    /**
     * Digits after the point the currency has (the decimals of its LOV item).
     */
    public function decimals(): int
    {
        return Currency::decimalsOf($this->currency);
    }

    public function scale(): int
    {
        return self::scaleOf($this->amount);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->withAmount($this->number()->plus($other->number()));
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->withAmount($this->number()->minus($other->number()));
    }

    /**
     * Amount times a number, e.g. a unit price times a quantity; the result is not rounded.
     */
    public function multiply(int|float|string|BigDecimal $factor): self
    {
        return $this->withAmount($this->number()->multipliedBy(self::toDecimalString($factor)));
    }

    /**
     * Amount rounded half away from zero to the given digits after the point, by default to the decimals of the currency.
     */
    public function round(?int $decimals = null): self
    {
        return $this->withAmount($this->number()->toScale($decimals ?? $this->decimals(), RoundingMode::HalfUp));
    }

    public function negate(): self
    {
        return $this->withAmount($this->number()->negated());
    }

    public function abs(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->number()->compareTo($other->number());
    }

    public function equals(self $other): bool
    {
        return $other->currency === $this->currency && $other->amount === $this->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === '0';
    }

    public function isNegative(): bool
    {
        return str_starts_with($this->amount, '-');
    }

    public function isPositive(): bool
    {
        return !$this->isZero() && !$this->isNegative();
    }

    /**
     * Amount with the decimals of the currency and the separators of the current locale, without the currency.
     */
    public function formatAmount(?int $decimals = null): string
    {
        $decimals ??= $this->decimals();

        $rounded = self::toDecimalString($this->number()->toScale($decimals, RoundingMode::HalfUp));

        [$integer, $fraction] = array_pad(explode('.', ltrim($rounded, '-'), 2), 2, '');

        $result = strrev(implode(strrev(NumberHelper::thousandsSeparator()), str_split(strrev($integer), 3)));

        if ($decimals > 0) {
            $result .= NumberHelper::decimalSeparator().str_pad($fraction, $decimals, '0');
        }

        return (str_starts_with($rounded, '-') ? '-' : '').$result;
    }

    /**
     * Amount with the currency symbol (the code when the currency has none): "1 234,50 $".
     */
    public function format(?int $decimals = null): string
    {
        return $this->formatAmount($decimals)."\u{00A0}".Currency::symbolOf($this->currency);
    }

    public function toFieldValue(): array
    {
        return [
            self::KEY_AMOUNT => str_contains($this->amount, '.') ? (float)$this->amount : (int)$this->amount,
            self::KEY_CURRENCY => $this->currency,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toFieldValue();
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
