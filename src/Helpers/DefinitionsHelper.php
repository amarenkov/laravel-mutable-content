<?php

namespace Amarenkov\MutableContent\Helpers;

use InvalidArgumentException;
use Throwable;

use Illuminate\Support\Facades\DB;

use Amarenkov\MutableContent\Database\Log\LogContext;
use Amarenkov\MutableContent\Domain\Field\Field as DomainField;
use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Models\Field\Field;
use Amarenkov\MutableContent\Models\Field\Usage;
use Amarenkov\MutableContent\Models\Lov\Item;
use Amarenkov\MutableContent\Models\Lov\Lov;
use Amarenkov\MutableContent\Models\ModelWithFields;

/**
 * Fields, usages, LOVs and items created in the admin panel, moved between environments by their codes.
 * System records come from the code and are neither exported nor changed by an import.
 */
class DefinitionsHelper
{
    // const
    public const VERSION = 2;

    public const LOVS = 'lovs';
    public const ITEMS = 'items';
    public const FIELDS = 'fields';
    public const USAGES = 'usages';

    public const KEY_LOV = 'lov';
    public const KEY_FIELD = 'field';

    public const RESULT_CREATED = 'created';
    public const RESULT_UPDATED = 'updated';
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_SKIPPED = 'skipped';

    // static
    /**
     * @return array{version: int, lovs: array, items: array, fields: array, usages: array}
     */
    public static function export(): array
    {
        $lovCodes = Lov::query()->pluck(DomainField::COMMON_CODE_CODE, 'id')->all();
        $fieldCodes = Field::query()->pluck(DomainField::COMMON_CODE_CODE, 'id')->all();

        return [
            'version' => self::VERSION,
            self::LOVS => static::rows(Lov::query()->orderBy('id')->get()),
            self::ITEMS => static::rows(Item::query()->orderBy('id')->get(), Item::FIELD_LOV_ID, self::KEY_LOV, $lovCodes),
            self::FIELDS => static::rows(Field::query()->orderBy('id')->get()),
            self::USAGES => static::rows(Usage::query()->orderBy('id')->get(), Usage::FIELD_FIELD_ID, self::KEY_FIELD, $fieldCodes),
        ];
    }

    /**
     * Create or update records by their codes in one transaction.
     *
     * @return array<string, array<string, int>> section => result => count
     */
    public static function import(array $data, ?string $comment = null, ?int $userId = null, bool $dryRun = false): array
    {
        if (($data['version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported definitions version');
        }

        $result = [];

        DB::beginTransaction();

        try {
            app(LogContext::class)->run(function () use ($data, &$result) {
                foreach ($data[self::LOVS] ?? [] as $fields) {
                    static::count($result, self::LOVS, static::upsert(Lov::query()->where(DomainField::COMMON_CODE_CODE, $fields[DomainField::COMMON_CODE_CODE] ?? null)->first(), new Lov(), $fields));
                }

                app(LovRegistry::class)->flush();

                foreach ($data[self::ITEMS] ?? [] as $fields) {
                    $lovId = Lov::query()->where(DomainField::COMMON_CODE_CODE, $fields[self::KEY_LOV] ?? null)->value('id');

                    if ($lovId === null) {
                        static::count($result, self::ITEMS, self::RESULT_SKIPPED);
                        continue;
                    }

                    $fields = [Item::FIELD_LOV_ID => $lovId] + array_diff_key($fields, [self::KEY_LOV => true]);

                    $existing = Item::query()->whereField(Item::FIELD_LOV_ID, $lovId)->whereField(DomainField::COMMON_CODE_CODE, $fields[DomainField::COMMON_CODE_CODE] ?? null)->first();

                    static::count($result, self::ITEMS, static::upsert($existing, new Item(), $fields));
                }

                foreach ($data[self::FIELDS] ?? [] as $fields) {
                    static::count($result, self::FIELDS, static::upsert(Field::query()->where(DomainField::COMMON_CODE_CODE, $fields[DomainField::COMMON_CODE_CODE] ?? null)->first(), new Field(), $fields));
                }

                foreach ($data[self::USAGES] ?? [] as $fields) {
                    $fieldId = Field::query()->where(DomainField::COMMON_CODE_CODE, $fields[self::KEY_FIELD] ?? null)->value('id');

                    if ($fieldId === null) {
                        static::count($result, self::USAGES, self::RESULT_SKIPPED);
                        continue;
                    }

                    $fields = [Usage::FIELD_FIELD_ID => $fieldId] + array_diff_key($fields, [self::KEY_FIELD => true]);

                    $existing = Usage::query()->whereField(Usage::FIELD_FIELD_ID, $fieldId)->whereField(Usage::CODE_SCOPE, $fields[Usage::CODE_SCOPE] ?? null)->first();

                    static::count($result, self::USAGES, static::upsert($existing, new Usage(), $fields));
                }
            }, array_filter(['comment' => $comment, 'user_id' => $userId], fn ($value) => $value !== null));

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        ModelWithFields::flushFieldDefinitions();
        app(LovRegistry::class)->flush();

        return $result;
    }

    /**
     * @param iterable<ModelWithFields> $records
     */
    protected static function rows(iterable $records, ?string $idField = null, ?string $codeKey = null, array $codes = []): array
    {
        $result = [];

        foreach ($records as $record) {
            if ($record->isSystem()) {
                continue;
            }

            $fields = $record->fields ? $record->fields->getArrayCopy() : [];

            if ($idField !== null) {
                $fields = [$codeKey => $codes[$fields[$idField] ?? null] ?? null] + array_diff_key($fields, [$idField => true]);
            }

            $result[] = $fields;
        }

        return $result;
    }

    protected static function upsert(?ModelWithFields $existing, ModelWithFields $new, array $fields): string
    {
        unset($fields[DomainField::COMMON_CODE_IS_SYSTEM]);

        if ($existing?->isSystem()) {
            return self::RESULT_SKIPPED;
        }

        $record = $existing ?? $new;
        $record->mergeWithFields($fields);

        if ($record->exists && !$record->isDirty()) {
            return self::RESULT_UNCHANGED;
        }

        $created = !$record->exists;
        $record->save();

        return $created ? self::RESULT_CREATED : self::RESULT_UPDATED;
    }

    protected static function count(array &$result, string $section, string $outcome): void
    {
        $result[$section][$outcome] = ($result[$section][$outcome] ?? 0) + 1;
    }
}
