<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Amarenkov\MutableContent\Models\Concerns\HasChangeLog;

class Asset extends Model
{
    use HasUuids;
    use SoftDeletes;
    use HasChangeLog;

    // protected
    public $timestamps = false;

    protected $fillable = ['title'];
}
