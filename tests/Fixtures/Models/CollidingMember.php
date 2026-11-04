<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

#[Table('members')]
class CollidingMember extends Member
{
    // const
    #[CFAType(Type::TYPE_STRING), CFALabel('Name')]
    public const FIELD_NAME = 'name';
}
