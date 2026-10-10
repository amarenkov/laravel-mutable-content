<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as FieldLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\LovCode as CFALovCode;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\ForType as CFAForType;

use Amarenkov\MutableContent\Tests\Fixtures\Lovs\TicketKind;

#[Table('fixtures.tickets')]
#[ClassLabel('Ticket')]
#[FieldLabel]
class Ticket extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_LOV_ITEM), CFALabel('Kind'), CFALovCode(TicketKind::CODE)]
    public const FIELD_KIND = 'kind';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Steps to reproduce'), CFAForType(TicketKind::BUG)]
    public const FIELD_STEPS = 'steps';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    public static function typeField(): ?string
    {
        return self::FIELD_KIND;
    }
}
