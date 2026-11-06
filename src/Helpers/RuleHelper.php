<?php

namespace Amarenkov\MutableContent\Helpers;

use Amarenkov\MutableContent\Domain\Field\Lov\Type as FieldType;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Domain\Field\Types\FieldTypes;

use Amarenkov\MutableContent\Rules\NotZero as RuleNotZero;

class RuleHelper
{
    /**
     * Rules of a number field by the allow_negative and allow_zero type settings.
     *
     * @return array<string|object>
     */
    public static function getSignRules($field): array
    {
        $allowsNegative = TypeSettings::allowsNegative($field);
        $allowsZero = TypeSettings::allowsZero($field);

        if (!$allowsNegative) {
            return [$allowsZero ? 'min:0' : 'gt:0'];
        }

        return $allowsZero ? [] : [new RuleNotZero()];
    }

    /**
     * @param ?array<string> $scopes usage scopes, see ModelWithFields::getFieldScopes()
     */
    public static function getValidationRules($class, $prefix = null, ?array $scopes = null)
    {
        $result = [];

        if ($prefix) {
            $prefix .= '.';
        }

        $fields = $class::getFieldDefinitions($scopes);

        $maxLengths = $class::getFieldMaxLengths();

        foreach ($fields as $field) {
            $usage = $field->usage();

            if ($usage->isImmutable || $field->fieldType === FieldType::TYPE_SYSTEM) {
                continue;
            }

            $rules = FieldTypes::for($field->fieldType)->rules($field);

            if (isset($maxLengths[$field->code])) {
                $rules[] = 'max:'.$maxLengths[$field->code];
            }

            if ($usage->isRequired) {
                $rules[] = 'required';
            } else {
                array_unshift($rules, 'nullable');
            }

            $result[$prefix.$field->code] = $rules;
        }

        return $result;
    }

    /**
     * Field labels as validator attribute names, keyed as in getValidationRules().
     */
    public static function getValidationAttributes($class, $prefix = null, ?array $scopes = null)
    {
        $result = [];

        if ($prefix) {
            $prefix .= '.';
        }

        foreach ($class::getFieldDefinitions($scopes) as $field) {
            if ($field->fieldType === FieldType::TYPE_SYSTEM) {
                continue;
            }

            if ($field->label !== null && $field->label !== '') {
                $result[$prefix.$field->code] = $field->label;
            }
        }

        return $result;
    }

    /**
     * Keeps only the fields getValidationRules() builds rules for.
     */
    public static function getValidatedFields($class, array $data, ?array $scopes = null)
    {
        return array_intersect_key($data, self::getValidationRules($class, null, $scopes));
    }
}