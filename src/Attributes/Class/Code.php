<?php

namespace Amarenkov\MutableContent\Attributes\Class;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Code
{
    public function __construct(
        public string $code
    ) {}
}