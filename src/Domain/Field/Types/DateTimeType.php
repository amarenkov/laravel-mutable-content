<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Contracts\MutableModel;
use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Models\ModelWithFields;

class DateTimeType extends FieldType
{
    // public
    public function normalize(mixed $value, MutableModel $model, string $code): mixed
    {
        return $model::toDateTimeValue($value);
    }

    public function queryValue(mixed $value): mixed
    {
        return ModelWithFields::toDateTimeValue($value);
    }

    public function rules(Field $field): array
    {
        return ['date'];
    }
}
