<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use DateTimeInterface;

use Amarenkov\MutableContent\Contracts\MutableModel;
use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

/**
 * What a field type means to the package: how a value is stored, compared, validated and shown, and which settings the type has.
 * This class is a plain text value; see FieldTypes for the type of a code.
 */
class FieldType
{
    // const
    public const KIND_STRING = 'string';
    public const KIND_INTEGER = 'integer';
    public const KIND_NUMBER = 'number';
    public const KIND_BOOLEAN = 'boolean';

    // protected
    protected function stringify(mixed $value): string
    {
        return is_array($value) ? (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$value;
    }

    // public
    /**
     * Value to store; a value that cannot be turned into the stored form is kept for the validation to reject.
     */
    public function normalize(mixed $value, MutableModel $model, string $code): mixed
    {
        return $value;
    }

    /**
     * Value to compare the stored ones with in a query.
     */
    public function queryValue(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    public function comparesAsNumber(Field $field): bool
    {
        return false;
    }

    /**
     * Validation rules of a value.
     *
     * @return array<string|object>
     */
    public function rules(Field $field): array
    {
        return ['string'];
    }

    /**
     * Human-readable value that is not empty.
     */
    public function format(Field $field, mixed $value): string
    {
        return $this->stringify($value);
    }

    /**
     * Settings of the type and their allowed values.
     *
     * @return array<string, array<string|int|bool>>
     */
    public function settings(): array
    {
        return [];
    }

    /**
     * Value of a setting that is not set.
     */
    public function defaultSetting(string $code): mixed
    {
        return in_array($code, [TypeSettings::ALLOW_ZERO, TypeSettings::ALLOW_NEGATIVE], true) ? false : null;
    }

    /**
     * Value object class of a type with units, or null.
     *
     * @return ?class-string<\Amarenkov\MutableContent\ValueObjects\HasUnits>
     */
    public function unitClass(): ?string
    {
        return null;
    }

    /**
     * JSON kind of a value: one of KIND_*.
     */
    public function kind(): string
    {
        return self::KIND_STRING;
    }
}
