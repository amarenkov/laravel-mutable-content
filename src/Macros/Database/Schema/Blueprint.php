<?php

namespace Amarenkov\MutableContent\Macros\Database\Schema;

use Illuminate\Support\Collection;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;

class Blueprint
{
    public static function add()
    {
        BaseBlueprint::macro('fieldsBase', function () {
            return new Collection([
                $this->increments('id'),
                
                $this->jsonb('fields'),

                $this->timestamp('created_at')->useCurrent(),
            ]);
        });

        BaseBlueprint::macro('fieldsUpdatedAt', function () {
            return $this->timestamp('updated_at')->useCurrent();
        });

        BaseBlueprint::macro('fieldExtract', function ($column) {
            return $this->addCommand('fieldExtract', ['column' => $column]);
        });
    }
}