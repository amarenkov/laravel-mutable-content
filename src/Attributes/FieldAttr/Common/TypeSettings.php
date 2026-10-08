<?php

namespace Amarenkov\MutableContent\Attributes\FieldAttr\Common;

use Attribute;

use Amarenkov\MutableContent\Attributes\FieldAttr\FieldAttr;

use Amarenkov\MutableContent\Domain\Field\Field;

/**
 * Field type settings set in code: setting => value. Settings from the DB override them per key.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
class TypeSettings extends FieldAttr 
{
    /**
     * @param array<string, mixed> $value
     */
    public function __construct(array $value) {
        parent::__construct(
            Field::CODE_FIELD_TYPE_SETTINGS,
            $value
        );
    }
}
