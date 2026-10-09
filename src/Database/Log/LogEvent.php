<?php

namespace Amarenkov\MutableContent\Database\Log;

use LogicException;

use Illuminate\Database\Query\Expression;

use Amarenkov\MutableContent\Domain\Log\LogData;

use Amarenkov\MutableContent\Helpers\LogHelper;

use Amarenkov\MutableContent\Models\ModelWithFields;

/**
 * Event without field changes for the object log (LogHelper::ACTION_EVENT), written by write().
 * The event is a translation key or a text, shown with __($event, scalar values of the data).
 * Its data is merged over the pending log context of the model, which stays for the next save.
 */
class LogEvent extends LogData
{
    // public
    public function __construct(
        protected ModelWithFields $model,
        protected string $event,
        array $data = []
    ) {
        $this->data($data);
    }

    public function write(): void
    {
        $model = $this->model;

        if (!$model->exists) {
            throw new LogicException('Cannot write log of unsaved '.$model::class.' object');
        }

        $data = array_replace($model->withLogContext()->toArray(), $this->toArray());
        $data[LogData::EVENT] = $this->event;

        app(LogContext::class)->run(function () use ($model) {
            $connection = $model->getConnection();

            $context = LogContext::sqlExpressions($connection);

            $connection->table(LogHelper::getLogsTable($model::class))->insert([
                LogHelper::COLUMN_ENTITY_ID => $model->getKey(),
                LogHelper::COLUMN_ACTION => LogHelper::ACTION_EVENT,
                LogHelper::COLUMN_DATA => new Expression($context[LogContext::DATA]),
                LogHelper::COLUMN_TRANSACTION_ID => new Expression($context[LogContext::TRANSACTION_ID]),
            ]);
        }, $data, $model->getConnectionName());
    }
}
