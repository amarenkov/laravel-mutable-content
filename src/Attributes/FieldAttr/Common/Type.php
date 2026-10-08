<?php

namespace Amarenkov\MutableContent\Attributes\FieldAttr\Common;

use Attribute;

use Amarenkov\MutableContent\Attributes\FieldAttr\FieldAttr;

use Amarenkov\MutableContent\Domain\Field\Field;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
class Type extends FieldAttr 
{
    public function __construct(string $value) {
        parent::__construct(
            Field::CODE_FIELD_TYPE,
            $value
        );
    }
}