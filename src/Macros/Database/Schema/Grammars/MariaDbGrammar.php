<?php

namespace Amarenkov\MutableContent\Macros\Database\Schema\Grammars;

use Illuminate\Support\Fluent;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MariaDbGrammar as BaseMariaDbGrammar;

class MariaDbGrammar
{
    // const
    public const TYPES = [
        'float' => 'double',
        'float8' => 'double',
        'double precision' => 'double',
        'real' => 'float',
        'float4' => 'float',
        'int4' => 'int',
        'integer' => 'int',
        'int8' => 'bigint',
        'int2' => 'smallint',
        'bool' => 'boolean',
    ];

    // static
    public static function compileFieldExtract(BaseMariaDbGrammar $grammar, Blueprint $blueprint, Fluent $command): string
    {
        $column = $command->get('column');
        $type = self::TYPES[strtolower((string)$command->get('type'))] ?? $command->get('type');

        $expression = $command->get('expression') ?: 'json_value('.$grammar->wrap('fields').", '$.\"".$column."\"')";

        return "
            ALTER TABLE {$grammar->wrapTable($blueprint)} ADD COLUMN {$grammar->wrap($column)} {$type} AS (
                {$expression}
            ) STORED
        ";
    }
}
