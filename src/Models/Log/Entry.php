<?php

namespace Amarenkov\MutableContent\Models\Log;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

use Amarenkov\MutableContent\Helpers\LogHelper;

/**
 * Read-only change log entry, built from LogHelper::query() via fromQuery().
 */
class Entry extends Model
{
    // static
    protected $table = 'log_entries';

    public $timestamps = false;

    /**
     * Eloquent query over LogHelper::query() or a union of them. id is the row number, newest first.
     */
    public static function fromQuery(QueryBuilder $logQuery): Builder
    {
        $numbered = $logQuery->newQuery()
            ->fromSub($logQuery, 'log_entries')
            ->select('log_entries.*')
            ->selectRaw('row_number() over (order by '.LogHelper::COLUMN_DATE.' desc, '.LogHelper::COLUMN_OBJECT_CLASS.', '.LogHelper::COLUMN_ENTITY_ID.' desc) as id');

        $model = new static();

        return $model->newQuery()->fromSub($numbered, $model->getTable());
    }

    // protected
    protected function casts(): array
    {
        return [
            LogHelper::COLUMN_FIELDS_OLD => 'array',
            LogHelper::COLUMN_FIELDS_NEW => 'array',
            LogHelper::COLUMN_IS_DELETED => 'boolean',
            LogHelper::COLUMN_DATE => 'datetime',
        ];
    }

    // public
    /**
     * One of LogHelper::ACTION_*.
     */
    public function action(): string
    {
        return LogHelper::getAction($this->{LogHelper::COLUMN_FIELDS_OLD}, $this->{LogHelper::COLUMN_FIELDS_NEW}, (bool)$this->{LogHelper::COLUMN_IS_DELETED});
    }

    /**
     * Human-readable description: the event text or the changed fields.
     *
     * @return array<string>
     */
    public function describe(): array
    {
        if ($this->action() === LogHelper::ACTION_EVENT) {
            return array_filter([(string)$this->{LogHelper::COLUMN_COMMENT}]);
        }

        return $this->describeChanges();
    }

    /**
     * Human-readable changed fields.
     *
     * @return array<string>
     */
    public function describeChanges(): array
    {
        return LogHelper::describeChanges($this->{LogHelper::COLUMN_OBJECT_CLASS}, $this->{LogHelper::COLUMN_FIELDS_OLD}, $this->{LogHelper::COLUMN_FIELDS_NEW});
    }
}
