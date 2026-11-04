<?php

namespace Amarenkov\MutableContent\Models\Concerns;

use Amarenkov\MutableContent\Database\Log\LogContext;
use Amarenkov\MutableContent\Database\Log\LogEvent;

use Amarenkov\MutableContent\Models\Log\ModelLogContext;

/**
 * Author, comment and events for the change log of the table made by Schema::addLog() or Schema::createWithLog().
 */
trait HasChangeLog
{
    // protected
    protected ?ModelLogContext $pendingLogContext = null;

    /**
     * Run a write with the log data set by withLogContext(), forgotten after a successful write.
     */
    protected function runInLogContext(callable $callback): mixed
    {
        $result = app(LogContext::class)->run($callback, $this->pendingLogContext ?? [], $this->getConnectionName());

        if ($result !== false) {
            $this->pendingLogContext = null;
        }

        return $result;
    }

    // public
    /**
     * Event without field changes for the object log, written by write() of the result.
     * The event is a translation key or a text, shown with __($event, scalar values of the data).
     */
    public function logEvent(string $event, array $data = []): LogEvent
    {
        return new LogEvent($this, $event, $data);
    }

    /**
     * Context of the log entry written automatically by the next save() or delete(): author, comment and anything else.
     * The comment is set when given and kept otherwise.
     */
    public function withLogContext(?string $comment = null): ModelLogContext
    {
        $this->pendingLogContext ??= new ModelLogContext($this);

        return $comment === null ? $this->pendingLogContext : $this->pendingLogContext->comment($comment);
    }

    public function __clone()
    {
        $this->pendingLogContext = null;
    }

    public function save(array $options = [])
    {
        return $this->runInLogContext(fn () => parent::save($options));
    }

    public function delete()
    {
        return $this->runInLogContext(fn () => parent::delete());
    }
}
