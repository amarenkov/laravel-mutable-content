<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Contracts\MutableModel;
use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Helpers\RuleHelper;

class IntType extends NumberType
{
    // public
    public function normalize(mixed $value, MutableModel $model, string $code): mixed
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $value;
    }

    public function rules(Field $field): array
    {
        return ['integer', ...RuleHelper::getSignRules($field)];
    }

    public function kind(): string
    {
        return self::KIND_INTEGER;
    }
}
