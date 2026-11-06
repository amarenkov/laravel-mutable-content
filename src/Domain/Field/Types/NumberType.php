<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Helpers\RuleHelper;

/**
 * A number: signed ones allow zero and negative values unless set otherwise, the others only when set.
 */
class NumberType extends FieldType
{
    // public
    public function __construct(
        protected bool $signed = true,
    )
    {
    }

    public function comparesAsNumber(Field $field): bool
    {
        return true;
    }

    public function rules(Field $field): array
    {
        return ['numeric', ...RuleHelper::getSignRules($field)];
    }

    public function settings(): array
    {
        return [
            TypeSettings::ALLOW_NEGATIVE => [true, false],
            TypeSettings::ALLOW_ZERO => [true, false],
        ];
    }

    public function defaultSetting(string $code): mixed
    {
        return in_array($code, [TypeSettings::ALLOW_ZERO, TypeSettings::ALLOW_NEGATIVE], true) ? $this->signed : parent::defaultSetting($code);
    }

    public function kind(): string
    {
        return self::KIND_NUMBER;
    }
}
