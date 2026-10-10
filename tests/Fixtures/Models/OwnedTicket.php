<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as FieldLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\ObjectClass as CFAObjectClass;

#[Table('fixtures.tickets')]
#[ClassLabel('Owned ticket')]
#[FieldLabel]
class OwnedTicket extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_OBJECT), CFALabel('Owner'), CFAObjectClass(Owner::class)]
    public const FIELD_OWNER_ID = 'owner_id';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    public static function typeField(): ?string
    {
        return self::FIELD_OWNER_ID;
    }
}
