<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Domain\Field\Field;

class IconType extends FieldType
{
    // public
    public function rules(Field $field): array
    {
        return ['string', 'max:255'];
    }
}
