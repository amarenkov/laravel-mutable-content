<?php

namespace Amarenkov\MutableContent\Database\Log;

use Throwable;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Support\Facades\DB;

use Amarenkov\MutableContent\Domain\Log\LogData;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;

/**
 * Data of the change log entries (author, comment, anything else), passed to the log triggers through the database session.
 */
class LogContext
{
    // const
    public const DATA = 'data';
    public const TRANSACTION_ID = 'transaction_id';

    public const PGSQL_SETTING = 'mutable_content.data';
    public const MARIADB_VARIABLE_PREFIX = '@mutable_content_log_';

    // static
    /**
     * SQL expressions reading the context inside the database: data and transaction_id.
     *
     * @return array<string, string>
     */
    public static function sqlExpressions(Connection $connection): array
    {
        if (DatabaseHelper::isMariaDb($connection)) {
            return [
                self::DATA => self::MARIADB_VARIABLE_PREFIX.self::DATA,
                self::TRANSACTION_ID => self::MARIADB_VARIABLE_PREFIX.self::TRANSACTION_ID,
            ];
        }

        return [
            self::DATA => "nullif(current_setting('".self::PGSQL_SETTING."', true), '')::jsonb",
            self::TRANSACTION_ID => 'pg_current_xact_id()::text::bigint',
        ];
    }

    // protected
    protected array $data = [];

    /**
     * @var array<string, array<array>>
     */
    protected array $stack = [];

    /**
     * @var array<string, int>
     */
    protected array $transactionIds = [];

    protected function current(string $connectionName): array
    {
        return array_replace($this->data, ...($this->stack[$connectionName] ?? []));
    }

    protected function apply(Connection $connection, bool $withContext, bool $withTransactionId): void
    {
        $data = $withContext ? $this->current($connection->getName()) : [];

        $data = $data ? Json::encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        if (DatabaseHelper::isMariaDb($connection)) {
            $transactionId = $withTransactionId ? $this->transactionIds[$connection->getName()] ??= random_int(1, PHP_INT_MAX) : null;

            $connection->statement(
                'SET '.self::MARIADB_VARIABLE_PREFIX.self::DATA.' = ?, '.self::MARIADB_VARIABLE_PREFIX.self::TRANSACTION_ID.' = ?',
                [$data, $transactionId]
            );

            return;
        }

        $connection->statement("select set_config('".self::PGSQL_SETTING."', ?, true)", [$data ?? '']);
    }

    /**
     * Back to the outer run, or to no context outside runs. A failed run is rolled back on PostgreSQL with its settings.
     * MariaDB session variables outlive transactions, so they keep nothing outside runs.
     */
    protected function restore(Connection $connection, bool $failed): void
    {
        $inOuterTransaction = $connection->transactionLevel() > 1;
        $inOuterRun = !empty($this->stack[$connection->getName()]);

        if (DatabaseHelper::isMariaDb($connection)) {
            $this->apply($connection, $inOuterRun, $inOuterRun);

            return;
        }

        if (!$failed && $inOuterTransaction) {
            $this->apply($connection, $inOuterRun, false);
        }
    }

    // public
    /**
     * Data of every log context run until clear(): for a request, a job or a command.
     */
    public function set(LogData|array $data): static
    {
        $this->data = $data instanceof LogData ? $data->toArray() : $data;

        return $this;
    }

    public function clear(): static
    {
        return $this->set([]);
    }

    public function isEmpty(): bool
    {
        return !$this->data;
    }

    /**
     * Run the callback in a transaction with the data applied to the connection, so the log triggers see it.
     * Data of nested runs is merged over the outer one by top-level keys.
     */
    public function run(callable $callback, LogData|array $data = [], ?string $connection = null): mixed
    {
        $data = $data instanceof LogData ? $data->toArray() : $data;

        $connection = DB::connection($connection);
        $name = $connection->getName();

        if (!$data && (!empty($this->stack[$name]) || $this->isEmpty())) {
            return $callback();
        }

        return $connection->transaction(function () use ($callback, $connection, $name, $data) {
            $this->stack[$name][] = $data;

            try {
                $this->apply($connection, true, true);

                $result = $callback();
            } catch (Throwable $exception) {
                array_pop($this->stack[$name]);

                rescue(fn () => $this->restore($connection, true), report: false);

                throw $exception;
            }

            array_pop($this->stack[$name]);

            $this->restore($connection, false);

            return $result;
        });
    }

    /**
     * Forget the transaction id of the connection when its outermost transaction ends.
     */
    public function transactionFinished(Connection $connection): void
    {
        if ($connection->transactionLevel() === 0) {
            unset($this->transactionIds[$connection->getName()]);
        }
    }
}
