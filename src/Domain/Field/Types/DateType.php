<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use DateTimeInterface;

use Amarenkov\MutableContent\Contracts\MutableModel;
use Amarenkov\MutableContent\Domain\Field\Field;

class DateType extends FieldType
{
    // public
    public function normalize(mixed $value, MutableModel $model, string $code): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    public function rules(Field $field): array
    {
        return ['date_format:Y-m-d'];
    }
}
