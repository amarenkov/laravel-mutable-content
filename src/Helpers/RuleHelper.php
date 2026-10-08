<?php

namespace Amarenkov\MutableContent\Helpers;

use Amarenkov\MutableContent\Domain\Field\Lov\Type as FieldType;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

use Amarenkov\MutableContent\Rules\InLovs as RuleInLovs;
use Amarenkov\MutableContent\Rules\InLov as RuleInLov;
use Amarenkov\MutableContent\Rules\LovItemCode as RuleLovItemCode;
use Amarenkov\MutableContent\Rules\ObjectCode as RuleObjectCode;
use Amarenkov\MutableContent\Rules\ObjectCodeExists as RuleObjectCodeExists;
use Amarenkov\MutableContent\Rules\ObjectExists as RuleObjectExists;

class RuleHelper
{
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
            $rules = [];

            $usage = $field->usage();

            if ($usage->isImmutable || $field->fieldType === FieldType::TYPE_SYSTEM) {
                continue;
            }

            switch ($field->fieldType) {
                case FieldType::TYPE_BOOL:
                    $rules[] = 'boolean';
                    break;

                case FieldType::TYPE_FLOAT:
                    $rules[] = 'numeric';
                    break;

                case FieldType::TYPE_INT:
                    $rules[] = 'integer';
                    break;

                case FieldType::TYPE_WEIGHT:
                    $rules[] = 'numeric';
                    $rules[] = TypeSettings::allowsZero($field) ? 'min:0' : 'gt:0';
                    break;

                case FieldType::TYPE_DENSITY:
                    $rules[] = 'numeric';
                    $rules[] = TypeSettings::allowsZero($field) ? 'min:0' : 'gt:0';
                    break;

                case FieldType::TYPE_SURFACE_DENSITY:
                    $rules[] = 'numeric';
                    $rules[] = TypeSettings::allowsZero($field) ? 'min:0' : 'gt:0';
                    break;

                case FieldType::TYPE_LENGTH:
                    $rules[] = 'numeric';
                    $rules[] = TypeSettings::allowsZero($field) ? 'min:0' : 'gt:0';
                    break;

                case FieldType::TYPE_AREA:
                    $rules[] = 'numeric';
                    $rules[] = TypeSettings::allowsZero($field) ? 'min:0' : 'gt:0';
                    break;

                case FieldType::TYPE_VOLUME:
                    $rules[] = 'numeric';
                    $rules[] = TypeSettings::allowsZero($field) ? 'min:0' : 'gt:0';
                    break;

                case FieldType::TYPE_DATE:
                    $rules[] = 'date_format:Y-m-d';
                    break;

                case FieldType::TYPE_ICON:
                    $rules[] = 'string';
                    $rules[] = 'max:255';
                    break;

                case FieldType::TYPE_LOV:
                    $rules[] = new RuleInLovs();
                    break;

                case FieldType::TYPE_LOV_ITEM:
                    $rules[] = TypeSettings::allowsUnlistedCodes($field) ? new RuleLovItemCode($field->lovCode) : new RuleInLov($field->lovCode);
                    break;

                case FieldType::TYPE_OBJECT:
                    if (TypeSettings::linksByCode($field)) {
                        $rules[] = TypeSettings::allowsUnlistedCodes($field) ? new RuleObjectCode($field->objectClass) : new RuleObjectCodeExists($field->objectClass);
                        break;
                    }

                    $rules[] = 'integer';

                    if ($field->objectClass) {
                        $rules[] = new RuleObjectExists($field->objectClass);
                    }
                    break;

                default:
                    $rules[] = 'string';
                    break;
            }

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