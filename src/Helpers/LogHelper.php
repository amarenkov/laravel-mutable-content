<?php

namespace Amarenkov\MutableContent\Helpers;

use InvalidArgumentException;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\Lov\Type as FieldType;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
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
    public const COLUMN_FIELDS_OLD = 'fields_old';
    public const COLUMN_FIELDS_NEW = 'fields_new';
    public const COLUMN_IS_DELETED = 'is_deleted';
    public const COLUMN_DATE = 'date';
    public const COLUMN_USER_ID = 'user_id';
    public const COLUMN_COMMENT = 'comment';

    public const COLUMN_OBJECT_CLASS = 'object_class';
    public const COLUMN_LOG_ID = 'log_id';

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';

    public const ACTION_EVENT = 'event';

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
        return SchemaBuilder::getLogsTableName(new $class()->getTable());
    }

    /**
     * Log entries of the class with object_class; combine classes with union.
     *
     * @param class-string<ModelWithFields> $class
     */
    public static function query(string $class): QueryBuilder
    {
        return DB::table(static::getLogsTable($class))
            ->select([
                self::COLUMN_ID.' as '.self::COLUMN_LOG_ID,
                self::COLUMN_ENTITY_ID,
                self::COLUMN_FIELDS_OLD,
                self::COLUMN_FIELDS_NEW,
                self::COLUMN_IS_DELETED,
                self::COLUMN_DATE,
                self::COLUMN_USER_ID,
                self::COLUMN_COMMENT,
            ])
            ->selectRaw('?::text as '.self::COLUMN_OBJECT_CLASS, [$class]);
    }

    /**
     * Log entry action: one of ACTION_*.
     */
    public static function getAction(?array $old, ?array $new, bool $isDeleted): string
    {
        if ($old === null && $new === null) {
            return self::ACTION_EVENT;
        }

        if ($old === null) {
            return self::ACTION_CREATED;
        }

        if (static::getChanges($old, $new)) {
            return self::ACTION_UPDATED;
        }

        return $isDeleted ? self::ACTION_DELETED : self::ACTION_RESTORED;
    }

    /**
     * Changed fields: code => [old, new].
     *
     * @return array<string, array{mixed, mixed}>
     */
    public static function getChanges(?array $old, ?array $new): array
    {
        $old ??= [];
        $new ??= [];

        $result = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $code) {
            $before = $old[$code] ?? null;
            $after = $new[$code] ?? null;

            if ($before !== $after) {
                $result[$code] = [$before, $after];
            }
        }

        return $result;
    }

    /**
     * Human-readable changed fields, labeled by the current class field definitions.
     *
     * @param class-string<ModelWithFields> $class
     * @return array<string>
     */
    public static function describeChanges(string $class, ?array $old, ?array $new): array
    {
        $fields = class_exists($class) ? $class::getFieldDefinitions() : [];

        $result = [];

        foreach (static::getChanges($old, $new) as $code => [$before, $after]) {
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
