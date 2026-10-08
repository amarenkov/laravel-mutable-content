<?php

namespace Amarenkov\MutableContent\Helpers;

use Illuminate\Database\Eloquent\Builder;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContent\Models\ModelWithFields;

/**
 * Objects referenced by object fields, by id or by code (TypeSettings::LINK_BY_CODE).
 */
class ObjectHelper
{
    // const
    public const OPTIONS_LIMIT = 50;

    public const TITLE_CODES = [Field::COMMON_CODE_LABEL, Field::COMMON_CODE_CODE];

    // static
    /**
     * Field value as an object id, or null.
     */
    public static function toId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int)$value;
        }

        return null;
    }

    /**
     * Ids of non-deleted objects with the code, up to $limit. More than one means the code is ambiguous.
     *
     * @param class-string<ModelWithFields> $class
     * @return array<int>
     */
    public static function getIdsByCode(string $class, string|int $code, int $limit = 2): array
    {
        $keyName = new $class()->getKeyName();

        return $class::query()
            ->where(self::codeColumn($class), (string)$code)
            ->orderBy($keyName)
            ->limit($limit)
            ->pluck($keyName)
            ->all();
    }

    /**
     * Codes of non-deleted objects: code => title; null if there are more than $limit.
     *
     * @param class-string<ModelWithFields> $class
     * @return ?array<string, string>
     */
    public static function getCodeOptions(string $class, int $limit = self::OPTIONS_LIMIT): ?array
    {
        $objects = $class::query()
            ->whereNotNull(self::codeColumn($class))
            ->orderBy(self::codeColumn($class))
            ->limit($limit + 1)
            ->get();

        if ($objects->count() > $limit) {
            return null;
        }

        $result = [];

        foreach ($objects as $object) {
            $result[(string)$object->getField(Field::COMMON_CODE_CODE)] = self::getTitle($object);
        }

        return $result;
    }

    public static function getClassLabel(string $class): string
    {
        return app(MutableClassRegistry::class)->getKeyValuePairs()[$class] ?? class_basename($class);
    }

    /**
     * Object title: label, else code, else id.
     */
    public static function getTitle(ModelWithFields $object): string
    {
        foreach (self::getTitleCodes($object::class) as $code) {
            $value = $object->getField($code);

            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }

        return '#'.$object->getKey();
    }

    /**
     * Object title by id; null if missing or deleted.
     *
     * @param class-string<ModelWithFields> $class
     */
    public static function getTitleById(string $class, mixed $id): ?string
    {
        $id = self::toId($id);

        return $id === null ? null : (self::getTitles($class, [$id])[$id] ?? null);
    }

    /**
     * Object titles by ids in one query; deleted and missing ones are skipped.
     *
     * @param class-string<ModelWithFields> $class
     * @return array<int, string>
     */
    public static function getTitles(string $class, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map([self::class, 'toId'], $ids), fn ($id) => $id !== null)));

        if (!$ids) {
            return [];
        }

        $result = [];

        foreach ($class::whereKey($ids)->get() as $object) {
            $result[$object->getKey()] = self::getTitle($object);
        }

        return $result;
    }

    /**
     * Object titles by codes in one query: code => title; deleted and missing ones are skipped.
     *
     * @param class-string<ModelWithFields> $class
     * @return array<string, string>
     */
    public static function getTitlesByCodes(string $class, array $codes): array
    {
        $codes = array_values(array_unique(array_map('strval', array_filter($codes, fn ($code) => is_string($code) || is_int($code)))));

        if (!$codes) {
            return [];
        }

        $result = [];

        foreach ($class::query()->whereIn(self::codeColumn($class), $codes)->orderByDesc(new $class()->getKeyName())->get() as $object) {
            $result[(string)$object->getField(Field::COMMON_CODE_CODE)] = self::getTitle($object);
        }

        return $result;
    }

    public static function getTitleByCode(string $class, mixed $code): ?string
    {
        return self::getTitlesByCodes($class, [$code])[(string)$code] ?? null;
    }

    /**
     * Select options: id => title, or code => title with $byCode. Searches by id, label and code.
     *
     * @param class-string<ModelWithFields> $class
     * @return array<int, string>
     */
    public static function getOptions(string $class, ?string $search = null, int $limit = self::OPTIONS_LIMIT, bool $byCode = false): array
    {
        $query = $class::query();

        $search = trim((string)$search);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($class, $search) {
                if ($id = self::toId($search)) {
                    $query->orWhereKey($id);
                }

                $like = '%'.addcslashes($search, '%_\\').'%';

                foreach (self::getTitleCodes($class) as $code) {
                    DatabaseHelper::orWhereLike($query, 'fields->'.$code, $like);
                }
            });
        }

        $result = [];

        if ($byCode) {
            $query->whereNotNull(self::codeColumn($class));
        }

        foreach ($query->orderBy($query->getModel()->getKeyName())->limit($limit)->get() as $object) {
            $result[$byCode ? (string)$object->getField(Field::COMMON_CODE_CODE) : $object->getKey()] = self::getTitle($object);
        }

        return $result;
    }

    /**
     * Query of non-deleted object codes, for whereIn subqueries.
     *
     * @param class-string<ModelWithFields> $class
     */
    public static function codesQuery(string $class): Builder
    {
        return $class::query()
            ->whereNotNull(self::codeColumn($class))
            ->select(self::codeColumn($class));
    }

    // protected
    protected static function codeColumn(string $class): string
    {
        return isset($class::getFieldMaxLengths()[Field::COMMON_CODE_CODE]) ? Field::COMMON_CODE_CODE : 'fields->'.Field::COMMON_CODE_CODE;
    }

    protected static function getTitleCodes(string $class): array
    {
        return array_values(array_intersect(self::TITLE_CODES, array_keys($class::getFieldDefinitions())));
    }
}
