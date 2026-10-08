<?php

namespace Amarenkov\MutableContent\Attributes\FieldAttr\Common;

use Attribute;

use Amarenkov\MutableContent\Attributes\FieldAttr\FieldAttr;

use Amarenkov\MutableContent\Domain\Field\Usage;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
class IsImmutable extends FieldAttr 
{
    public function __construct() {
        parent::__construct(
            Usage::CODE_IS_IMMUTABLE,
            true
        );
    }
}