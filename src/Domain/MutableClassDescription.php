<?php

namespace Amarenkov\MutableContent\Domain;

use ReflectionClass;

use InvalidArgumentException;

use Amarenkov\MutableContent\Attributes\Class\Label as AttributeClassLabel;

use Amarenkov\MutableContent\Contracts\MutableModel;

class MutableClassDescription
{
    public function __construct(
        public string $mutableClass
    ) {
        if (!class_exists($mutableClass)) {
            throw new InvalidArgumentException("Class {$mutableClass} does not exist.");
        }

        if (!is_subclass_of($mutableClass, MutableModel::class)) {
            throw new InvalidArgumentException("Class {$mutableClass} must implement ".MutableModel::class.'.');
        }

        $reflection = new ReflectionClass($mutableClass);

        $label = null;
        foreach ($reflection->getAttributes(AttributeClassLabel::class) as $attribute) {
            $label = $attribute->newInstance()->label;
        }

        if (!$label) {
            throw new InvalidArgumentException("Class {$mutableClass} must have Label attribute.");
        }

        $this->label = __($label);
    }

    public string $label;
}