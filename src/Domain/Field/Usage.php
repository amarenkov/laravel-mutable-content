<?php

namespace Amarenkov\MutableContent\Domain\Field;

class Usage
{
    // const
    public const CODE_SCOPE = 'scope';

    public const CODE_IS_REQUIRED = 'is_required';

    public const CODE_IS_IMMUTABLE = 'is_immutable';
    public const CODE_IS_IMMUTABLE_FOR_SYSTEM_OBJECTS = 'is_immutable_for_system_objects';

    // public
    public function __construct(string $scope)
    {
        $this->scope = $scope;
    }

    public function absorb(Usage $usage)
    {
        if ($usage->isRequired && !$this->isRequired) {
            $this->isRequired($usage->isRequired);
        }

        if ($usage->isImmutable && !$this->isImmutable) {
            $this->isImmutable($usage->isImmutable);
        }

        if ($usage->isImmutableForSystemObjects && !$this->isImmutableForSystemObjects) {
            $this->isImmutableForSystemObjects($usage->isImmutableForSystemObjects);
        }

        if ($usage->typeSettings) {
            $this->typeSettings(array_replace($this->typeSettings, $usage->typeSettings));
        }

        return $this;
    }

    public function isRequired(bool $value)
    {
        $this->isRequired = $value;

        return $this;
    }

    public function isImmutable(bool $value)
    {
        $this->isImmutable = $value;

        return $this;
    }

    public function isImmutableForSystemObjects(bool $value)
    {
        $this->isImmutableForSystemObjects = $value;

        return $this;
    }

    public function typeSettings(array $value)
    {
        $this->typeSettings = $value;

        return $this;
    }

    public function toArray()
    {
        return array_filter([
            self::CODE_SCOPE => $this->scope,
            
            self::CODE_IS_IMMUTABLE => $this->isImmutable,
            self::CODE_IS_IMMUTABLE_FOR_SYSTEM_OBJECTS => $this->isImmutableForSystemObjects
        ]);
    }

    public string $scope;

    public bool $isRequired = false;

    public bool $isImmutable = false;
    public bool $isImmutableForSystemObjects = false;

    public array $typeSettings = [];
}