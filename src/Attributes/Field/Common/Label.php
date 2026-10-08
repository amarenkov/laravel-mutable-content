<?php

namespace Amarenkov\MutableContent\Attributes\Field\Common;

use Attribute;

use Amarenkov\MutableContent\Attributes\Field\Field as AttributeField;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\Lov\Type;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Label extends AttributeField 
{
    public function __construct() {
        parent::__construct(
            Field::COMMON_CODE_LABEL,
            Type::TYPE_STRING,
            'Label'
        );

        $this->isRequired = true;
    }
}