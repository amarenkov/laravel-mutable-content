<?php

namespace Amarenkov\MutableContent\Helpers;

use DateTimeInterface;
use InvalidArgumentException;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Casts\Json;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Log;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\Lov\Type as FieldType;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Domain\Log\Changes;
use Amarenkov\MutableContent\Domain\Log\LogData;
use Amarenkov\MutableContent\Domain\LovRegistry;

use Amarenkov\MutableContent\Macros\Database\Schema\Builder as SchemaBuilder;

use Amarenkov\MutableContent\Models\ModelWithFields;

/**
 * Reads the change log written by Schema::createWithLog() triggers.
 */
class LogHelper
{
    // const
    public const COLUMN_ID = 'id';
    public const COLUMN_ENTITY_ID = 'entity_id';
    public const COLUMN_ACTION = 'action';
    public const COLUMN_FIELDS_OLD = 'fields_old';
    public const COLUMN_FIELDS_NEW = 'fields_new';
    public const COLUMN_FIELDS_CHANGED = 'fields_changed';
    public const COLUMN_COMPRESSION_STATUS = 'compression_status';
    public const COLUMN_DATE = 'date';
    public const COLUMN_DATA = 'data';
    public const COLUMN_USER_ID = 'user_id';
    public const COLUMN_TRANSACTION_ID = 'transaction_id';

    public const COLUMN_OBJECT_CLASS = 'object_class';
    public const COLUMN_LOG_ID = 'log_id';

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';

    public const ACTION_EVENT = 'event';

    public const COMPRESSION_PENDING = 'pending';
    public const COMPRESSION_COMPRESSED = 'compressed';
    public const COMPRESSION_ERROR = 'error';

    public static function getActionLabel(string $action): string
    {
        $key = 'mutable-content::log.actions.'.$action;

        return trans()->has($key) ? __($key) : $action;
    }

    // static
    /**
     * @param class-string<ModelWithFields> $class
     */
    public static function getLogsTable(string $class): string
    {
        $model = new $class();

        return SchemaBuilder::getLogsTableName($model->getTable(), $model->getConnection());
    }

    /**
     * Log entries of the class with object_class; combine classes with union.
     *
     * @param class-string<ModelWithFields> $class
     */
    public static function query(string $class): QueryBuilder
    {
        $connection = new $class()->getConnection();

        return $connection->table(static::getLogsTable($class))
            ->select([
                self::COLUMN_ID.' as '.self::COLUMN_LOG_ID,
                self::COLUMN_ENTITY_ID,
                self::COLUMN_ACTION,
                self::COLUMN_FIELDS_OLD,
                self::COLUMN_FIELDS_NEW,
                self::COLUMN_FIELDS_CHANGED,
                self::COLUMN_COMPRESSION_STATUS,
                self::COLUMN_DATE,
                self::COLUMN_DATA,
                self::COLUMN_USER_ID,
                self::COLUMN_TRANSACTION_ID,
            ])
            ->selectRaw((DatabaseHelper::isMariaDb($connection) ? 'cast(? as char)' : '?::text').' as '.self::COLUMN_OBJECT_CLASS, [$class]);
    }

    /**
     * Replace old and new fields of pending entries with their changes in fields_changed, up to the limit.
     * An update without changes keeps its fields and gets the error status for review.
     * Parallel runs skip each other's entries.
     *
     * @param class-string<ModelWithFields> $class
     * @return int Processed entries.
     */
    public static function compress(string $class, int $limit = 10000, int $chunkSize = 500): int
    {
        $connection = new $class()->getConnection();
        $table = static::getLogsTable($class);

        $processed = 0;

        while ($processed < $limit) {
            $size = min($chunkSize, $limit - $processed);

            $count = $connection->transaction(function () use ($class, $connection, $table, $size) {
                $rows = $connection->table($table)
                    ->select([self::COLUMN_ID, self::COLUMN_ACTION, self::COLUMN_FIELDS_OLD, self::COLUMN_FIELDS_NEW])
                    ->where(self::COLUMN_COMPRESSION_STATUS, self::COMPRESSION_PENDING)
                    ->limit($size)
                    ->lock('for update skip locked')
                    ->get();

                $errors = [];

                foreach ($rows as $row) {
                    $changes = Changes::between(static::decode($row->{self::COLUMN_FIELDS_OLD}), static::decode($row->{self::COLUMN_FIELDS_NEW}));

                    if ($changes->isEmpty() && $row->{self::COLUMN_ACTION} === self::ACTION_UPDATED) {
                        $errors[] = $row->{self::COLUMN_ID};

                        continue;
                    }

                    $connection->table($table)->where(self::COLUMN_ID, $row->{self::COLUMN_ID})->update([
                        self::COLUMN_FIELDS_OLD => null,
                        self::COLUMN_FIELDS_NEW => null,
                        self::COLUMN_FIELDS_CHANGED => Json::encode((object)$changes->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        self::COLUMN_COMPRESSION_STATUS => self::COMPRESSION_COMPRESSED,
                    ]);
                }

                if ($errors) {
                    $connection->table($table)->whereIn(self::COLUMN_ID, $errors)->update([self::COLUMN_COMPRESSION_STATUS => self::COMPRESSION_ERROR]);

                    Log::warning('Mutable content: log entries of '.$class.' are updates without changes, left uncompressed', ['ids' => $errors]);
                }

                return count($rows);
            });

            $processed += $count;

            if ($count < $size) {
                break;
            }
        }

        return $processed;
    }

    /**
     * Delete entries older than the date, in chunks of separate statements.
     *
     * @param class-string<ModelWithFields> $class
     * @return int Deleted entries.
     */
    public static function prune(string $class, DateTimeInterface $before, int $chunkSize = 10000): int
    {
        $connection = new $class()->getConnection();
        $table = static::getLogsTable($class);
        $date = static::dateBinding($before, $connection);

        $deleted = 0;

        do {
            $count = $connection->table($table)->where(self::COLUMN_DATE, '<', $date)->limit($chunkSize)->delete();

            $deleted += $count;
        } while ($count === $chunkSize);

        return $deleted;
    }

    /**
     * Date for a comparison with the date column: with its offset on PostgreSQL, where the column is timestamptz.
     */
    protected static function dateBinding(DateTimeInterface $date, Connection $connection): DateTimeInterface|string
    {
        return DatabaseHelper::isMariaDb($connection) ? $date : $date->format('Y-m-d H:i:s.uP');
    }

    public static function decode(mixed $value): ?array
    {
        if ($value === null || is_array($value)) {
            return $value;
        }

        $value = Json::decode($value);

        return is_array($value) ? $value : null;
    }

    /**
     * Human-readable changes, labeled by the current class field definitions.
     *
     * @param class-string<ModelWithFields> $class
     * @return array<string>
     */
    public static function describeChanges(string $class, Changes $changes): array
    {
        $fields = class_exists($class) ? $class::getFieldDefinitions() : [];

        $result = [];

        foreach ($changes->items as $code => [$before, $after]) {
            $field = $fields[$code] ?? null;

            $label = $field?->label ?: $code;

            $before = static::formatValue($field, $before);
            $after = static::formatValue($field, $after);

            $result[] = match (true) {
                $before === null => $label.': '.$after,
                $after === null => $label.': '.$before.' → '.__('mutable-content::log.empty'),
                default => $label.': '.$before.' → '.$after,
            };
        }

        return $result;
    }

    /**
     * Event text of an entry written by logEvent(): the translated event, with scalar values of the data as replacements.
     */
    public static function describeEvent(?array $data): ?string
    {
        $event = $data[LogData::EVENT] ?? null;

        if (!is_string($event) || $event === '') {
            return null;
        }

        $replace = array_filter(array_diff_key($data, [LogData::EVENT => true]), fn ($value) => is_scalar($value));

        return __($event, $replace);
    }

    /**
     * Human-readable field value, or null if empty.
     */
    public static function formatValue(?Field $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($field === null) {
            return is_bool($value) ? ($value ? __('mutable-content::log.yes') : __('mutable-content::log.no')) : (string)$value;
        }

        switch ($field->fieldType) {
            case FieldType::TYPE_BOOL:
                return $value ? __('mutable-content::log.yes') : __('mutable-content::log.no');

            case FieldType::TYPE_LOV_ITEM:
                return app(LovRegistry::class)->getLovItemLabel($field->lovCode, $value) ?? (string)$value;

            case FieldType::TYPE_LOV:
                return app(LovRegistry::class)->getLovLabel($value) ?? (string)$value;

            case FieldType::TYPE_OBJECT:
                if (!$field->objectClass) {
                    return (string)$value;
                }

                $title = TypeSettings::linksByCode($field)
                    ? ObjectHelper::getTitleByCode($field->objectClass, $value)
                    : ObjectHelper::getTitleById($field->objectClass, $value);

                return $title ?? (string)$value;
        }

        if ($unitClass = TypeSettings::getUnitClass($field->fieldType)) {
            try {
                return $unitClass::fromFieldValue($value)?->format(unit: TypeSettings::getValue($field, TypeSettings::DISPLAY_UNIT)) ?? (string)$value;
            } catch (InvalidArgumentException) {
                return (string)$value;
            }
        }

        return (string)$value;
    }
}
