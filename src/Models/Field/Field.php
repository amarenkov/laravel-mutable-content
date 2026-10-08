<?php

namespace Amarenkov\MutableContent\Models\Field;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\HasMany;

use ArrayObject;
use InvalidArgumentException;

use Amarenkov\MutableContent\Domain\Field\Field as DomainField;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

use Amarenkov\MutableContent\Domain\Field\Lov\Type as DomainLovFieldType;

use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContent\Helpers\FieldCodeHelper;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as AttributeClassLabel;

use Amarenkov\MutableContent\Attributes\Field\Common\Code as CommonFieldCode;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as CommonFieldLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Description as CommonFieldDescription;
use Amarenkov\MutableContent\Attributes\Field\Common\IsSystem as CommonFieldIsSystem;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\LovCode as CFALovCode;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutableForSystemObjects as CFAIsImmutableForSystemObjects;

#[Table('fields')]

#[AttributeClassLabel('Field')]

#[CommonFieldCode()]
#[CommonFieldLabel()]
#[CommonFieldDescription()]
#[CommonFieldIsSystem()]
class Field extends ModelWithFields
{
    // const
    #[CFAType(DomainLovFieldType::TYPE_LOV_ITEM), CFALabel('Field type'), CFAIsRequired, CFAIsImmutableForSystemObjects, CFALovCode(DomainLovFieldType::CLASS_CODE)]
    const CODE_FIELD_TYPE = DomainField::CODE_FIELD_TYPE;

    #[CFAType(DomainLovFieldType::TYPE_LOV), CFALabel('LOV'), CFAIsImmutableForSystemObjects]
    const CODE_LOV_CODE = DomainField::CODE_LOV_CODE;

    #[CFAType(DomainLovFieldType::TYPE_LOV_ITEM), CFALabel('Object class'), CFAIsImmutableForSystemObjects, CFALovCode(MutableClassRegistry::LOV_CODE)]
    const CODE_OBJECT_CLASS = DomainField::CODE_OBJECT_CLASS;

    #[CFAType(DomainLovFieldType::TYPE_SYSTEM), CFALabel('Type settings')]
    const CODE_FIELD_TYPE_SETTINGS = DomainField::CODE_FIELD_TYPE_SETTINGS;
    
    // static
    protected static array|bool|null $fieldDefinitions = null;

    public static function uniqueFieldSets(): array
    {
        return [[DomainField::COMMON_CODE_CODE]];
    }

    protected static function booted(): void
    {
        static::saving(function (Field $field) {
            $code = (string)$field->code();

            $error = FieldCodeHelper::getError($code);

            if (!$error && $field->{DomainField::CODE_FIELD_TYPE} === DomainLovFieldType::TYPE_SYSTEM && !$field->isSystem()) {
                $error = __('mutable-content::validation.field_type_system');
            }

            if (!$error && $field->exists) {
                foreach ($field->usages()->pluck(Usage::CODE_SCOPE)->map(fn ($scope) => Usage::getScopeMutableClass($scope))->filter()->unique() as $mutableClass) {
                    $error ??= FieldCodeHelper::getErrorForClass($code, $mutableClass);
                }
            }

            if ($error) {
                throw new InvalidArgumentException($error);
            }

            $typeSettings = TypeSettings::normalize((string)$field->{DomainField::CODE_FIELD_TYPE}, $field->{self::CODE_FIELD_TYPE_SETTINGS});

            if ($field->{self::CODE_FIELD_TYPE_SETTINGS} != $typeSettings) {
                $field->{self::CODE_FIELD_TYPE_SETTINGS} = $typeSettings;
            }
        });

        foreach (['saved', 'deleted', 'restored'] as $event) {
            static::$event(function () {
                ModelWithFields::flushFieldDefinitions();
            });
        }
    }
    
    // public
    public function usages(): HasMany
    {
        return $this->hasMany(Usage::class, 'field_id');
    }

    public function setSystemFields(DomainField $field)
    {
        $this->{DomainField::COMMON_CODE_IS_SYSTEM} = true;
        $this->{DomainField::CODE_FIELD_TYPE} = $field->fieldType;

        foreach ([DomainField::CODE_LOV_CODE => $field->lovCode, DomainField::CODE_OBJECT_CLASS => $field->objectClass] as $code => $value) {
            if ($this->{$code} !== $value) {
                $this->{$code} = $value;
            }
        }

        if ($field->typeSettings) {
            $current = $this->{self::CODE_FIELD_TYPE_SETTINGS};
            $settings = $current instanceof ArrayObject ? $current->getArrayCopy() : (array)($current ?? []);
            $merged = $settings + $field->typeSettings;

            if ($merged != $settings) {
                $this->{self::CODE_FIELD_TYPE_SETTINGS} = $merged;
            }
        }
    }
}
