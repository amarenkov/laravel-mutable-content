<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Code as FieldCode;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\LovCode as CFALovCode;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\ObjectClass as CFAObjectClass;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\TypeSettings as CFATypeSettings;

use Amarenkov\MutableContent\Tests\Fixtures\Lovs\RecordStatus;

#[Table('fixtures.records')]
#[ClassLabel('Record')]
#[FieldCode]
class Record extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_INT), CFALabel('Quantity'), CFAIsRequired]
    public const FIELD_QUANTITY = 'quantity';

    #[CFAType(Type::TYPE_WEIGHT), CFALabel('Weight')]
    public const FIELD_WEIGHT = 'weight';

    #[CFAType(Type::TYPE_BOOL), CFALabel('Active')]
    public const FIELD_IS_ACTIVE = 'is_active';

    #[CFAType(Type::TYPE_DATE), CFALabel('Due date')]
    public const FIELD_DUE_DATE = 'due_date';

    #[CFAType(Type::TYPE_DATETIME), CFALabel('Started at')]
    public const FIELD_STARTED_AT = 'started_at';

    #[CFAType(Type::TYPE_LOV_ITEM), CFALabel('Status'), CFALovCode(RecordStatus::CODE)]
    public const FIELD_STATUS = 'status';

    #[CFAType(Type::TYPE_OBJECT), CFALabel('Owner'), CFAObjectClass(Owner::class)]
    public const FIELD_OWNER_ID = 'owner_id';

    #[CFAType(Type::TYPE_OBJECT), CFALabel('Owner code'), CFAObjectClass(Owner::class), CFATypeSettings([TypeSettings::LINK_BY_CODE => true])]
    public const FIELD_OWNER_CODE = 'owner_code';

    #[CFAType(Type::TYPE_STRING), CFALabel('External id'), CFAIsImmutable]
    public const FIELD_EXTERNAL_ID = 'external_id';

    #[CFAType(Type::TYPE_SYSTEM), CFALabel('Sync state')]
    public const FIELD_SYNC_STATE = 'sync_state';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, self::FIELD_OWNER_ID);
    }
}
