<?php

namespace Amarenkov\MutableContent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Attributes\Fillable;

use Amarenkov\MutableContent\Contracts\MutableModel;

use Amarenkov\MutableContent\Models\Concerns\HasChangeLog;
use Amarenkov\MutableContent\Models\Concerns\HasMutableFields;

/**
 * Model with fields, soft deletes and the change log, on a table made by Schema::createWithLog().
 */
#[Fillable(['fields'])]
class ModelWithFields extends Model implements MutableModel
{
    use SoftDeletes;
    use HasMutableFields;
    use HasChangeLog;
}
