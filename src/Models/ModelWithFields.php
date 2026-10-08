<?php

namespace Amarenkov\MutableContent\Models;

use DateTimeInterface;
use LogicException;
use Throwable;

use ReflectionClass;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\Lov\Type as FieldType;

use Amarenkov\MutableContent\Attributes\FieldAttr\FieldAttr;
use Amarenkov\MutableContent\Attributes\Lov\ItemField as AttributeLovItemField;

use Amarenkov\MutableContent\Helpers\FieldCodeHelper;
use Amarenkov\MutableContent\Helpers\LogHelper;

use Amarenkov\MutableContent\Models\Field\Usage as UsageModel;

use Amarenkov\MutableContent\ValueObjects\Area;
use Amarenkov\MutableContent\ValueObjects\Density;
use Amarenkov\MutableContent\ValueObjects\FieldValue;
use Amarenkov\MutableContent\ValueObjects\Length;
use Amarenkov\MutableContent\ValueObjects\SurfaceDensity;
use Amarenkov\MutableContent\ValueObjects\Volume;
use Amarenkov\MutableContent\ValueObjects\Weight;

#[Fillable(['fields'])]
class ModelWithFields extends Model
{
    use SoftDeletes;

    // const
    public const COLUMN_UPDATED_BY_USER_ID = 'updated_by_user_id';
    public const COLUMN_UPDATED_WITH_COMMENT = 'updated_with_comment';

    public const COMMENT_MAX_LENGTH = 255;

    // static
    protected static array|bool|null $fieldDefinitions = false;

    protected static array $fieldDefinitionsCachedIn = [];

    protected static array $fieldMaxLengths = [];

    /**
     * Reset cached field definitions of all classes.
     */
    public static function flushFieldDefinitions(): void
    {
        foreach (self::$fieldDefinitionsCachedIn as $class) {
            $class::$fieldDefinitions = null;
        }

        self::$fieldDefinitionsCachedIn = [];
    }

    /**
     * Scope of a field usage bound to this class. Override together with fillFieldUsageFromScope().
     */
    public static function fieldUsageScope(UsageModel $usage): string
    {
        $lovCode = $usage->{UsageModel::CODE_LOV_CODE};

        if ($lovCode !== null && $lovCode !== '') {
            return UsageModel::makeScope(UsageModel::CODE_LOV_CODE, $lovCode);
        }

        return UsageModel::makeScope(UsageModel::CODE_MUTABLE_CLASS, $usage->{UsageModel::CODE_MUTABLE_CLASS});
    }

    /**
     * Fill the usage fields from the scope; the inverse of fieldUsageScope().
     */
    public static function fillFieldUsageFromScope(UsageModel $usage, string $scope): void
    {
        [$type, $value] = UsageModel::parseScope($scope);

        foreach ([UsageModel::CODE_MUTABLE_CLASS, UsageModel::CODE_LOV_CODE] as $code) {
            $codeValue = $type === $code ? $value : null;

            if ($usage->{$code} !== $codeValue) {
                $usage->{$code} = $codeValue;
            }
        }
    }

    /**
     * Usage scopes of the class when there is no object.
     *
     * @return array<string>
     */
    public static function getFieldScopes(): array
    {
        return [static::getClassScope()];
    }

    public static function getClassScope(): string
    {
        return UsageModel::makeScope(UsageModel::CODE_MUTABLE_CLASS, static::class);
    }

    /**
     * Field sets unique including soft-deleted rows. An insert matching a deleted row restores it.
     *
     * @return array<array<string>>
     */
    public static function uniqueFieldSets(): array
    {
        return [];
    }

    /**
     * Max lengths of fields extracted to varchar(N) columns: code => N.
     *
     * @return array<string, int>
     */
    public static function getFieldMaxLengths(): array
    {
        if (!isset(self::$fieldMaxLengths[static::class])) {
            $model = new static();

            $result = [];

            foreach ($model->getConnection()->getSchemaBuilder()->getColumns($model->getTable()) as $column) {
                if ($column['generation'] !== null && preg_match('/^(?:character varying|character)\((\d+)\)$/', $column['type'], $matches)) {
                    $result[$column['name']] = (int)$matches[1];
                }
            }

            self::$fieldMaxLengths[static::class] = $result;
        }

        return self::$fieldMaxLengths[static::class];
    }

    public static function getSystemFields()
    {
        $result = [];

        $reflection = new ReflectionClass(static::class);

        foreach ($reflection->getAttributes() as $attribute) {
            if (strpos($attribute->getName(), 'Amarenkov\\MutableContent\\Attributes\\Field\\') === 0) {
                $field = $attribute->newInstance()->toDomainField(static::class);

                $result[$field->code] = $field;
            }
        }

        foreach ($reflection->getReflectionConstants() as $reflectionConstant) {
            if ($reflectionConstant->getAttributes(AttributeLovItemField::class)) {
                continue;
            }

            $attrs = FieldAttr::collect($reflectionConstant);

            if ($attrs) {
                $field = FieldAttr::buildDomainField(static::class, $reflectionConstant->getValue(), $attrs);

                if (@$result[$field->code]) {
                    throw new LogicException($field->code.' field is already in class '.static::class.' fields list');
                }

                $result[$field->code] = $field;
            }
        }

        foreach ($result as $field) {
            if ($error = FieldCodeHelper::getErrorForClass($field->code, static::class)) {
                throw new LogicException($error);
            }
        }

        return $result;
    }
    
    /**
     * Class field definitions for the scopes, getFieldScopes() by default. Cached per scope set.
     *
     * @param ?array<string> $scopes
     */
    public static function getFieldDefinitions(?array $scopes = null)
    {
        if (static::$fieldDefinitions === false) {
            throw new LogicException('Public static $fieldDefinitions must be rediclared in a child class '.static::class.' with value null');
        }

        $scopes ??= static::getFieldScopes();

        $cacheKey = implode("\n", $scopes);

        if (!isset(static::$fieldDefinitions[$cacheKey])) {
            $definitions = [];

            foreach (static::getSystemFields() as $field) {
                $usages = array_intersect_key($field->usages, array_flip($scopes));

                if (!$usages) {
                    continue;
                }

                $field = clone $field;
                $field->usages = $usages;

                $definitions[$field->code] = $field;
            }

            static::$fieldDefinitions ??= [];
            static::$fieldDefinitions[$cacheKey] = $definitions;

            try {
                foreach (UsageModel::whereIn(UsageModel::CODE_SCOPE, $scopes)->whereHas('field')->with('field')->orderBy('id')->get() as $usageModel) {
                    $field = $usageModel->toDomainField();

                    if ($error = FieldCodeHelper::getErrorForClass($field->code, static::class)) {
                        Log::warning('Mutable content: field skipped. '.$error);

                        continue;
                    }

                    $existed = $definitions[$field->code] ?? null;

                    if ($existed) {
                        $existed->absorb($field);
                    } else {
                        $definitions[$field->code] = $field;
                    }
                }
            } catch (Throwable $exception) {
                unset(static::$fieldDefinitions[$cacheKey]);

                throw $exception;
            }

            static::$fieldDefinitions[$cacheKey] = $definitions;

            self::$fieldDefinitionsCachedIn[static::class] = static::class;
        }

        return static::$fieldDefinitions[$cacheKey];
    }

    public static function arrayMergeRecursiveDistinct(array &$array1, array &$array2)
    {
        $merged = $array1;
        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
    
    // protected
    protected function casts(): array
    {
        return [
            'fields' => AsArrayObject::class
        ];
    }

    protected function asJson($value, $flags = 0)
    {
        return Json::encode($value, $flags | JSON_UNESCAPED_UNICODE);
    }

    protected function isItField($key)
    {
        return key_exists($key, $this->fieldDefinitions());
    }

    protected function normalizeFieldValue($key, $value)
    {
        if ($value instanceof FieldValue) {
            $value = $value->toFieldValue();
        }

        $field = $this->fieldDefinitions()[$key] ?? null;

        if ($field && in_array($field->fieldType, [FieldType::TYPE_FLOAT, FieldType::TYPE_WEIGHT, FieldType::TYPE_DENSITY, FieldType::TYPE_SURFACE_DENSITY, FieldType::TYPE_LENGTH, FieldType::TYPE_AREA, FieldType::TYPE_VOLUME], true) && is_numeric($value)) {
            $value = (float)$value;
        }

        if ($field && $field->fieldType === FieldType::TYPE_INT && is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            $value = (int)$value;
        }

        if ($field && $field->fieldType === FieldType::TYPE_DATE && $value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        return $value;
    }

    protected function forgetUpdatedBy()
    {
        foreach ([self::COLUMN_UPDATED_BY_USER_ID, self::COLUMN_UPDATED_WITH_COMMENT] as $column) {
            if (array_key_exists($column, $this->attributes)) {
                $this->attributes[$column] = null;
                $this->original[$column] = null;
            }
        }
    }

    protected function finishSave(array $options)
    {
        parent::finishSave($options);

        $this->forgetUpdatedBy();
    }

    protected function findTrashedDuplicate(): ?static
    {
        foreach (static::uniqueFieldSets() as $codes) {
            $query = static::onlyTrashed();

            foreach ($codes as $code) {
                $value = $this->getField($code);

                if ($value === null) {
                    continue 2;
                }

                $query->where($code, $value);
            }

            $trashed = $query->orderByDesc($this->getDeletedAtColumn())->first();

            if ($trashed) {
                return $trashed;
            }
        }

        return null;
    }

    protected function performInsert(EloquentBuilder $query)
    {
        $trashed = $this->findTrashedDuplicate();

        if (!$trashed) {
            return parent::performInsert($query);
        }

        $fields = array_replace(
            $trashed->fields ? $trashed->fields->toArray() : [],
            $this->fields ? $this->fields->toArray() : []
        );

        $updatedBy = [];
        foreach ([self::COLUMN_UPDATED_BY_USER_ID, self::COLUMN_UPDATED_WITH_COMMENT] as $column) {
            if (array_key_exists($column, $this->attributes)) {
                $updatedBy[$column] = $this->attributes[$column];
            }
        }

        $this->classCastCache = [];
        $this->attributeCastCache = [];

        $this->setRawAttributes($trashed->getAttributes(), true);
        $this->exists = true;

        parent::setAttribute('fields', $fields);

        foreach ($updatedBy as $column => $value) {
            $this->attributes[$column] = $value;
        }

        $this->{$this->getDeletedAtColumn()} = null;

        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $saved = $this->performUpdate($query);

        if ($saved) {
            $this->fireModelEvent('restored', false);
        }

        return $saved;
    }

    protected function runSoftDelete()
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        foreach ([self::COLUMN_UPDATED_BY_USER_ID, self::COLUMN_UPDATED_WITH_COMMENT] as $column) {
            if (array_key_exists($column, $this->attributes)) {
                $columns[$column] = $this->attributes[$column];
            }
        }

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->forgetUpdatedBy();

        $this->fireModelEvent('trashed', false);
    }

    // public
    /**
     * Usage scopes of this object.
     *
     * @return array<string>
     */
    public function fieldScopes(): array
    {
        return static::getFieldScopes();
    }

    public function fieldDefinitions(): array
    {
        return static::getFieldDefinitions($this->fieldScopes());
    }

    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        if (isset($attributes['fields']) && is_array($attributes['fields'])) {
            foreach ($this->fieldDefinitions() as $code => $field) {
                if ($field->fieldType === FieldType::TYPE_SYSTEM) {
                    unset($attributes['fields'][$code]);
                }
            }
        }

        return $attributes;
    }

    public function mergeWithFields($data)
    {
        $fields = $this->fields ? $this->fields->toArray() : [];

        $fields = self::arrayMergeRecursiveDistinct($fields, $data);

        $fields = array_filter($fields, fn ($value) => $value !== null);

        return parent::setAttribute('fields', $fields);
    }

    public function setField($name, $value)
    {
        $fields = $this->fields ?? [];

        $value = $this->normalizeFieldValue($name, $value);

        if ($value === null) {
            unset($fields[$name]);
        } else {
            $fields[$name] = $value;
        }

        return parent::setAttribute('fields', $fields);
    }

    public function getField($name, $default = null)
    {
        return $this->fields[$name] ?? $default;
    }

    public function getWeight($name): ?Weight
    {
        return Weight::fromFieldValue($this->getField($name));
    }

    public function getDensity($name): ?Density
    {
        return Density::fromFieldValue($this->getField($name));
    }

    public function getSurfaceDensity($name): ?SurfaceDensity
    {
        return SurfaceDensity::fromFieldValue($this->getField($name));
    }

    public function getLength($name): ?Length
    {
        return Length::fromFieldValue($this->getField($name));
    }

    public function getArea($name): ?Area
    {
        return Area::fromFieldValue($this->getField($name));
    }

    public function getVolume($name): ?Volume
    {
        return Volume::fromFieldValue($this->getField($name));
    }

    public function getDate($name): ?Carbon
    {
        $value = $this->getField($name);

        return $value === null ? null : Carbon::createFromFormat('!Y-m-d', $value);
    }

    public function getAttribute($key)
    {
        if ($this->isItField($key))
            return $this->getField($key);

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if ($this->isItField($key))
            return $this->setField($key, $value);

        return parent::setAttribute($key, $value);
    }

    /**
     * Write an event without field changes to the object log (LogHelper::ACTION_EVENT).
     */
    public function writeLog(string $message, ?int $userId = null): void
    {
        if (!$this->exists) {
            throw new LogicException('Cannot write log of unsaved '.static::class.' object');
        }

        DB::table(LogHelper::getLogsTable(static::class))->insert([
            LogHelper::COLUMN_ENTITY_ID => $this->getKey(),
            LogHelper::COLUMN_IS_DELETED => $this->{$this->getDeletedAtColumn()} !== null,
            LogHelper::COLUMN_USER_ID => $userId,
            LogHelper::COLUMN_COMMENT => mb_substr($message, 0, self::COMMENT_MAX_LENGTH),
        ]);
    }

    public function setUpdatedBy($comment = null, $userId = null)
    {
        if ($comment !== null) {
            $comment = mb_substr((string)$comment, 0, self::COMMENT_MAX_LENGTH);
        }

        $this->updated_with_comment = $comment;
        $this->updated_by_user_id = $userId;
    }

    public function setUpdatedByIfDirty($comment = null, $userId = null)
    {
        if ($this->isDirty()) {
            $this->setUpdatedBy($comment, $userId);
        }
    }

    public function fill(array $attributes)
    {
        $not_fields = $attributes;

        for ($pass = 0; $pass < 2 && $not_fields; $pass++) {
            $fields = [];
            $rest = [];

            foreach ($not_fields as $key => $value) {
                if ($this->isItField($key)) {
                    $fields[$key] = $this->normalizeFieldValue($key, $value);
                } else {
                    $rest[$key] = $value;
                }
            }

            if (!$fields) {
                break;
            }

            $this->mergeWithFields($fields);

            $not_fields = $rest;
        }

        return parent::fill($not_fields);
    }

    public function isSystem()
    {
        return (bool)$this->{Field::COMMON_CODE_IS_SYSTEM};
    }

    public function code()
    {
        return (string)$this->{Field::COMMON_CODE_CODE};
    }

    public function label()
    {
        return (string)$this->{Field::COMMON_CODE_LABEL};
    }
}
