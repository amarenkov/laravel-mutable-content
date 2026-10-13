<?php

namespace Amarenkov\MutableContent\Attributes\FieldAttr\Common;

use Attribute;

use Amarenkov\MutableContent\Attributes\FieldAttr\FieldAttr;

/**
 * Label of the field in this class only, over the label of the field. A label set in the usage in the DB overrides it.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
class UsageLabel extends FieldAttr
{
    // const
    public const CODE = 'usage_label';

    // public
    public function __construct(string $value) {
        parent::__construct(
            self::CODE,
            $value
        );
    }
}
