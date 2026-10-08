<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Code as FieldCode;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as FieldLabel;

#[Table('owners')]
#[ClassLabel('Owner')]
#[FieldCode]
#[FieldLabel]
class Owner extends ModelWithFields
{
    // static
    protected static array|bool|null $fieldDefinitions = null;

    public static function uniqueFieldSets(): array
    {
        return [['code']];
    }
}
