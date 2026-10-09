<?php

namespace Amarenkov\MutableContent\Models\Log;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

use Amarenkov\MutableContent\Domain\Log\Changes;
use Amarenkov\MutableContent\Domain\Log\LogData;
use Amarenkov\MutableContent\Helpers\LogHelper;

/**
 * Read-only change log entry, built from LogHelper::query() via fromQuery().
 *
 * @property int $log_id
 * @property int $entity_id
 * @property string $action
 * @property ?array $fields_old
 * @property ?array $fields_new
 * @property ?array $fields_changed
 * @property string $compression_status
 * @property \Illuminate\Support\Carbon $date
 * @property ?array $data
 * @property ?int $user_id
 * @property ?int $transaction_id
 * @property class-string $object_class
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
            ->selectRaw('row_number() over (order by '.LogHelper::COLUMN_DATE.' desc, '.LogHelper::COLUMN_OBJECT_CLASS.', '.LogHelper::COLUMN_LOG_ID.' desc) as id');

        $model = new static();

        return $model->newQuery()->fromSub($numbered, $model->getTable());
    }

    // protected
    protected function casts(): array
    {
        return [
            LogHelper::COLUMN_FIELDS_OLD => 'array',
            LogHelper::COLUMN_FIELDS_NEW => 'array',
            LogHelper::COLUMN_FIELDS_CHANGED => 'array',
            LogHelper::COLUMN_DATE => 'datetime',
            LogHelper::COLUMN_DATA => 'array',
        ];
    }

    /**
     * Dates in the application timezone: the database returns timestamptz in its session timezone.
     */
    protected function asDateTime($value)
    {
        return parent::asDateTime($value)->setTimezone(config('app.timezone'));
    }

    // public
    /**
     * One of LogHelper::ACTION_*.
     */
    public function action(): string
    {
        return (string)$this->{LogHelper::COLUMN_ACTION};
    }

    public function comment(): ?string
    {
        $comment = $this->{LogHelper::COLUMN_DATA}[LogData::COMMENT] ?? null;

        return is_scalar($comment) ? (string)$comment : null;
    }

    /**
     * Changed fields, from fields_changed of a compressed entry or from old and new fields.
     */
    public function changes(): Changes
    {
        if ($this->{LogHelper::COLUMN_COMPRESSION_STATUS} === LogHelper::COMPRESSION_COMPRESSED) {
            return new Changes($this->{LogHelper::COLUMN_FIELDS_CHANGED} ?? []);
        }

        return Changes::between($this->{LogHelper::COLUMN_FIELDS_OLD}, $this->{LogHelper::COLUMN_FIELDS_NEW});
    }

    /**
     * Human-readable description: the event text or the changed fields.
     *
     * @return array<string>
     */
    public function describe(): array
    {
        if ($this->action() === LogHelper::ACTION_EVENT) {
            return array_filter([LogHelper::describeEvent($this->{LogHelper::COLUMN_DATA}) ?? (string)$this->comment()]);
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
        return LogHelper::describeChanges($this->{LogHelper::COLUMN_OBJECT_CLASS}, $this->changes());
    }
}
