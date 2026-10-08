<?php

namespace Amarenkov\MutableContent\ValueObjects;

/**
 * Value object assignable to a model field; stored as toFieldValue().
 */
interface FieldValue
{
    public function toFieldValue(): mixed;
}
