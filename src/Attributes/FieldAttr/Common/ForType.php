<?php

namespace Amarenkov\MutableContent\Attributes\FieldAttr\Common;

use Attribute;

use Amarenkov\MutableContent\Attributes\FieldAttr\FieldAttr;

/**
 * Binds a field declared in code to one type of the class (see ModelWithFields::typeField()) instead of the whole class.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class ForType extends FieldAttr
{
    // const
    public const CODE = 'for_type';

    // public
    public function __construct(string $typeCode)
    {
        parent::__construct(self::CODE, $typeCode);
    }
}
