<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Lovs;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(RecordStatus::CODE)]
#[ClassLabel('Record status')]
#[LovItem(RecordStatus::DRAFT, 'Draft')]
#[LovItem(RecordStatus::ACTIVE, 'Active')]
class RecordStatus
{
    // const
    public const CODE = 'record_status';

    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
}
