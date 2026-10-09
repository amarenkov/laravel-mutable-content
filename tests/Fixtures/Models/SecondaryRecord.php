<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Models;

class SecondaryRecord extends Record
{
    // static
    protected static array|bool|null $fieldDefinitions = null;

    // protected
    protected $connection = 'secondary';
}
