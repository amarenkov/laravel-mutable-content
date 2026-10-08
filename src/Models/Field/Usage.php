<?php

namespace Amarenkov\MutableContent\Models\Field;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use InvalidArgumentException;
use LogicException;

use Amarenkov\MutableContent\Domain\Field\Field as DomainField;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Domain\Field\Usage as DomainFieldUsage;
use Amarenkov\MutableContent\Domain\Field\Lov\Type as DomainLovFieldType;

use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContent\Helpers\FieldCodeHelper;

use Amarenkov\MutableContent\Models\ModelWithFields;
use Amarenkov\MutableContent\Models\Lov\Item as LovItem;

use Amarenkov\MutableContent\Attributes\Class\Label as AttributeClassLabel;

use Amarenkov\MutableContent\Attributes\Field\Common\Description as AttributeFieldDescription;
use Amarenkov\MutableContent\Attributes\Field\Common\IsSystem as AttributeFieldIsSystem;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\LovCode as CFALovCode;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\ObjectClass as CFAObjectClass;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutableForSystemObjects as CFAIsImmutableForSystemObjects;

#[Table('fields_usage')]
#[Fillable(['id', 'fields'])]

#[AttributeClassLabel('Field usage')]

#[AttributeFieldDescription()]
#[AttributeFieldIsSystem()]
class Usage extends ModelWithFields
{
    // const
    #[CFAType(DomainLovFieldType::TYPE_OBJECT), CFALabel('Field'), CFAIsImmutable, CFAObjectClass(Field::class)]
    const FIELD_FIELD_ID = 'field_id';

    #[CFAType(DomainLovFieldType::TYPE_LOV_ITEM), CFALabel('Mutable class'), CFAIsImmutableForSystemObjects, CFALovCode(MutableClassRegistry::LOV_CODE)]
    const CODE_MUTABLE_CLASS = 'mutable_class';

    #[CFAType(DomainLovFieldType::TYPE_LOV), CFALabel('LOV'), CFAIsImmutableForSystemObjects]
    const CODE_LOV_CODE = DomainField::CODE_LOV_CODE;

    #[CFAType(DomainLovFieldType::TYPE_SYSTEM), CFALabel('Scope'), CFAIsImmutable]
    const CODE_SCOPE = DomainFieldUsage::CODE_SCOPE;

    const SCOPE_SEPARATOR = ':';

    const LOV_MUTABLE_CLASS = LovItem::class;

    #[CFAType(DomainLovFieldType::TYPE_BOOL), CFALabel('Required'), CFAIsImmutableForSystemObjects]
    const CODE_IS_REQUIRED = DomainFieldUsage::CODE_IS_REQUIRED;

    #[CFAType(DomainLovFieldType::TYPE_BOOL), CFALabel('Immutable'), CFAIsImmutable]
    const CODE_IS_IMMUTABLE = DomainFieldUsage::CODE_IS_IMMUTABLE;

    #[CFAType(DomainLovFieldType::TYPE_BOOL), CFALabel('Immutable for system objects'), CFAIsImmutable]
    const CODE_IS_IMMUTABLE_FOR_SYSTEM_OBJECTS = DomainFieldUsage::CODE_IS_IMMUTABLE_FOR_SYSTEM_OBJECTS;

    #[CFAType(DomainLovFieldType::TYPE_SYSTEM), CFALabel('Type settings')]
    const CODE_FIELD_TYPE_SETTINGS = DomainField::CODE_FIELD_TYPE_SETTINGS;

    // static
    protected static array|bool|null $fieldDefinitions = null;

    public static function uniqueFieldSets(): array
    {
        return [[self::FIELD_FIELD_ID, self::CODE_SCOPE]];
    }

    public static function makeScope(string $type, string $value): string
    {
        return $type.self::SCOPE_SEPARATOR.$value;
    }

    /**
     * Scope kind and value; the kind is null without a separator.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function parseScope(string $scope): array
    {
        $parts = explode(self::SCOPE_SEPARATOR, $scope, 2);

        return count($parts) === 2 ? $parts : [null, $scope];
    }

    protected static function booted(): void
    {
        static::saving(function (Usage $usage) {
            $hasMutableClass = (string)$usage->{self::CODE_MUTABLE_CLASS} !== '';
            $hasLovCode = (string)$usage->{self::CODE_LOV_CODE} !== '';

            if ($hasMutableClass === $hasLovCode) {
                throw new InvalidArgumentException(__('mutable-content::validation.usage_target'));
            }

            $field = Field::find($usage->{self::FIELD_FIELD_ID});
            $mutableClass = $usage->getMutableClass();

            if ($field && $mutableClass) {
                $error = FieldCodeHelper::getErrorForClass($field->code(), $mutableClass);

                if ($error) {
                    throw new InvalidArgumentException($error);
                }
            }

            $usage->fillScope();

            $typeSettings = TypeSettings::normalize((string)$field?->{DomainField::CODE_FIELD_TYPE}, $usage->{self::CODE_FIELD_TYPE_SETTINGS});

            if ($usage->{self::CODE_FIELD_TYPE_SETTINGS} != $typeSettings) {
                $usage->{self::CODE_FIELD_TYPE_SETTINGS} = $typeSettings;
            }
        });

        foreach (['saved', 'deleted', 'restored'] as $event) {
            static::$event(function () {
                ModelWithFields::flushFieldDefinitions();
            });
        }
    }

    // public
    /**
     * Fill the scope from the bound class (ModelWithFields::fieldUsageScope()).
     */
    public function fillScope(): void
    {
        $scope = static::scopeClass($this->getMutableClass())::fieldUsageScope($this);

        if ($this->{self::CODE_SCOPE} !== $scope) {
            $this->{self::CODE_SCOPE} = $scope;
        }
    }

    /**
     * Fill the usage from the scope; the inverse of fillScope().
     */
    public function fillFromScope(string $scope): void
    {
        static::scopeClass(static::getScopeMutableClass($scope))::fillFieldUsageFromScope($this, $scope);

        $this->fillScope();

        if ($this->{self::CODE_SCOPE} !== $scope) {
            throw new LogicException('Usage filled from scope "'.$scope.'" gives scope "'.$this->{self::CODE_SCOPE}.'"');
        }
    }

    protected static function scopeClass(?string $mutableClass): string
    {
        return $mutableClass && class_exists($mutableClass) && is_subclass_of($mutableClass, ModelWithFields::class)
            ? $mutableClass
            : ModelWithFields::class;
    }

    /**
     * Class the scope belongs to; the LOV item class for a LOV scope.
     */
    public static function getScopeMutableClass(string $scope): ?string
    {
        [$type, $value] = static::parseScope($scope);

        return match ($type) {
            self::CODE_MUTABLE_CLASS => $value,
            self::CODE_LOV_CODE => self::LOV_MUTABLE_CLASS,
            default => null,
        };
    }

    /**
     * Class the usage belongs to; the LOV item class for a LOV usage.
     */
    public function getMutableClass(): ?string
    {
        $mutableClass = $this->{self::CODE_MUTABLE_CLASS};

        if ((string)$mutableClass !== '') {
            return $mutableClass;
        }

        return (string)$this->{self::CODE_LOV_CODE} !== '' ? self::LOV_MUTABLE_CLASS : null;
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, self::FIELD_FIELD_ID);
    }

    public function toDomainField() : DomainField
    {
        $field = new DomainField(
            $this->field->fields[DomainField::COMMON_CODE_CODE],
            $this->field->fields[DomainField::CODE_FIELD_TYPE]
        )
            ->label(@$this->field->fields[DomainField::COMMON_CODE_LABEL])
            ->lovCode(@$this->field->fields[DomainField::CODE_LOV_CODE])
            ->objectClass($this->field->fields[DomainField::CODE_OBJECT_CLASS] ?? null)
            ->typeSettings($this->field->fields[DomainField::CODE_FIELD_TYPE_SETTINGS] ?? []);

        $field->addUsage(new DomainFieldUsage((string)$this->{self::CODE_SCOPE})
            ->isRequired($this->{self::CODE_IS_REQUIRED} ?? false)
            ->isImmutable($this->{self::CODE_IS_IMMUTABLE} ?? false)
            ->isImmutableForSystemObjects($this->{self::CODE_IS_IMMUTABLE_FOR_SYSTEM_OBJECTS} ?? false)
            ->typeSettings($this->{self::CODE_FIELD_TYPE_SETTINGS} ?? [])
        );

        return $field;
    }

    public function setSystemFields(DomainFieldUsage $usage)
    {
        $this->{DomainField::COMMON_CODE_IS_SYSTEM} = true;

        if ($this->{DomainFieldUsage::CODE_IS_REQUIRED} != $usage->isRequired) {
            $this->{DomainFieldUsage::CODE_IS_REQUIRED} = $usage->isRequired;
        }

        if ($this->{DomainFieldUsage::CODE_IS_IMMUTABLE} != $usage->isImmutable) {
            $this->{DomainFieldUsage::CODE_IS_IMMUTABLE} = $usage->isImmutable;
        }
        if ($this->{DomainFieldUsage::CODE_IS_IMMUTABLE_FOR_SYSTEM_OBJECTS} != $usage->isImmutableForSystemObjects) {
            $this->{DomainFieldUsage::CODE_IS_IMMUTABLE_FOR_SYSTEM_OBJECTS} = $usage->isImmutableForSystemObjects;
        }

        $this->fillFromScope($usage->scope);
    }
}
