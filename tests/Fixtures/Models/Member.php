<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;

use Amarenkov\MutableContent\Contracts\MutableModel;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Models\Concerns\HasChangeLog;
use Amarenkov\MutableContent\Models\Concerns\HasMutableFields;

#[ClassLabel('Member')]
class Member extends Model implements MutableModel
{
    use HasMutableFields;
    use HasChangeLog;

    // const
    #[CFAType(Type::TYPE_STRING), CFALabel('Nickname')]
    public const FIELD_NICKNAME = 'nickname';

    #[CFAType(Type::TYPE_INT), CFALabel('Rating')]
    public const FIELD_RATING = 'rating';

    // protected
    protected $fillable = ['name', 'status'];

    protected function casts(): array
    {
        return ['status' => 'string'];
    }
}
