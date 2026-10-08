<?php

namespace Amarenkov\MutableContent\Attributes\Lov;

use Attribute;

/**
 * Describes a field of the LOV items, not of the LOV itself.
 * The field is created as a system one and bound to the items of this LOV.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class ItemField
{
    
}
