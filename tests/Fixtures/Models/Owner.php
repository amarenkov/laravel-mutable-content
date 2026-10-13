<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Code as FieldCode;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as FieldLabel;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\UsageLabel as CFAUsageLabel;

#[Table('owners')]
#[ClassLabel('Owner')]
#[FieldCode]
#[FieldLabel]
class Owner extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_BOOL), CFALabel('Active'), CFAUsageLabel('Enabled')]
    public const FIELD_IS_ACTIVE = 'is_active';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    public static function uniqueFieldSets(): array
    {
        return [['code']];
    }
}
