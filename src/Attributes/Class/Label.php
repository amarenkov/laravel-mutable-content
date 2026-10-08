<?php

namespace Amarenkov\MutableContent\Attributes\Class;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Label
{
    public function __construct(
        public string $label
    ) {}
}