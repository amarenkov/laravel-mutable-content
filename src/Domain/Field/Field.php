<?php

namespace Amarenkov\MutableContent\Domain\Field;

use InvalidArgumentException;
use LogicException;

class Field
{
    // const
    public const COMMON_CODE_CODE = 'code';
    public const COMMON_CODE_LABEL = 'label';
    public const COMMON_CODE_DESCRIPTION = 'description';
    public const COMMON_CODE_IS_SYSTEM = 'is_system';

    public const CODE_FIELD_TYPE = 'field_type';
    public const CODE_FIELD_TYPE_SETTINGS = 'field_type_settings';

    public const CODE_LOV_CODE = 'lov_code';
    public const CODE_OBJECT_CLASS = 'object_class';

    // public
    public function __construct(
        public string $code,
        public string $fieldType
    )
    {
    }

    public function label(?string $value)
    {
        $this->label = $value;

        return $this;
    }

    public function lovCode(?string $value)
    {
        $this->lovCode = $value;

        return $this;
    }

    public function objectClass(?string $value)
    {
        $this->objectClass = $value;

        return $this;
    }

    public function typeSettings(array $value)
    {
        $this->typeSettings = $value;

        return $this;
    }

    public function addUsage(Usage $usage)
    {
        $existed = $this->usages[$usage->scope] ?? null;

        if ($existed) {
            $existed->absorb($usage);
        } else {
            $this->usages[$usage->scope] = $usage;
        }

        return $this;
    }

    /**
     * Usage merged from all scopes; flags can only be turned on.
     */
    public function usage(): Usage
    {
        $result = null;

        foreach ($this->usages as $usage) {
            if ($result) {
                $result->absorb($usage);
            } else {
                $result = clone $usage;
            }
        }

        if (!$result) {
            throw new LogicException($this->code.' field has no usages');
        }

        return $result;
    }

    /**
     * Type settings of the field, overridden by those of the merged usage.
     */
    public function getTypeSettings(): array
    {
        return array_replace($this->typeSettings, $this->usage()->typeSettings);
    }

    public function absorb(Field $source)
    {
        foreach ($source->usages as $usage) {
            $this->addUsage($usage);
        }

        $this->label($source->label);

        $this->typeSettings(array_replace($this->typeSettings, $source->typeSettings));

        return $this;
    }

    public function usagesToArray()
    {
        $result = [];

        foreach ($this->usages as $usage) {
            $result[] = $usage->toArray();
        }

        return $result;
    }

    public function attributesToArray()
    {
        return array_filter([
            self::COMMON_CODE_CODE => $this->code,
            self::COMMON_CODE_LABEL => $this->label,
            
            self::CODE_FIELD_TYPE => $this->fieldType,
            self::CODE_LOV_CODE => $this->lovCode,
            self::CODE_OBJECT_CLASS => $this->objectClass
        ]);
    }

    public function toArray()
    {
        $attributes = $this->attributesToArray();

        return array_merge($attributes, $this->usagesToArray());
    }

    public ?string $label = null;
    public ?string $lovCode = null;
    public ?string $objectClass = null;

    public array $typeSettings = [];

    public array $usages = [];
}