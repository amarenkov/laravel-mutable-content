<?php

namespace Amarenkov\MutableContent\Domain\Field\Lov;

use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;

use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(Type::CLASS_CODE)]
#[ClassLabel('Field type')]

#[LovItem(Type::TYPE_UNDEFINED, 'Undefined')]
#[LovItem(Type::TYPE_STRING, 'String')]
#[LovItem(Type::TYPE_TEXT, 'Text')]
#[LovItem(Type::TYPE_BOOL, 'Boolean')]
#[LovItem(Type::TYPE_INT, 'Integer')]
#[LovItem(Type::TYPE_FLOAT, 'Decimal')]
#[LovItem(Type::TYPE_LOV, 'LOV')]
#[LovItem(Type::TYPE_LOV_ITEM, 'LOV item')]
#[LovItem(Type::TYPE_ADDRESS, 'Address')]
#[LovItem(Type::TYPE_OBJECT, 'Object')]
#[LovItem(Type::TYPE_WEIGHT, 'Weight')]
#[LovItem(Type::TYPE_DENSITY, 'Density')]
#[LovItem(Type::TYPE_SURFACE_DENSITY, 'Surface density')]
#[LovItem(Type::TYPE_LENGTH, 'Length')]
#[LovItem(Type::TYPE_AREA, 'Area')]
#[LovItem(Type::TYPE_VOLUME, 'Volume')]
#[LovItem(Type::TYPE_DATE, 'Date')]
#[LovItem(Type::TYPE_ICON, 'Icon')]
#[LovItem(Type::TYPE_SYSTEM, 'System')]
class Type extends Lov
{
    // const
    public const CLASS_CODE = 'field_type';

    public const TYPE_UNDEFINED = 'undefined';
    public const TYPE_STRING = 'string';
    public const TYPE_TEXT = 'text';
    public const TYPE_BOOL = 'bool';
    public const TYPE_INT = 'int';
    public const TYPE_FLOAT = 'float';
    public const TYPE_LOV = 'lov';
    public const TYPE_LOV_ITEM = 'lov_item';
    public const TYPE_ADDRESS = 'address';
    public const TYPE_OBJECT = 'object';
    public const TYPE_WEIGHT = 'weight';
    public const TYPE_DENSITY = 'density';
    public const TYPE_SURFACE_DENSITY = 'surface_density';
    public const TYPE_LENGTH = 'length';
    public const TYPE_AREA = 'area';
    public const TYPE_VOLUME = 'volume';
    public const TYPE_DATE = 'date';
    public const TYPE_ICON = 'icon';
    public const TYPE_SYSTEM = 'system';
}