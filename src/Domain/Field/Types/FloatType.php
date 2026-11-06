<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Contracts\MutableModel;

class FloatType extends NumberType
{
    // public
    public function normalize(mixed $value, MutableModel $model, string $code): mixed
    {
        return is_numeric($value) ? (float)$value : $value;
    }
}
