<?php

namespace Amarenkov\MutableContent\Helpers;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContent\Models\ModelWithFields;

/**
 * Field code validation: a code must not clash with model columns or relations.
 */
class FieldCodeHelper
{
    // const
    public const PATTERN = '/^[a-z][a-z0-9_]*$/';

    public const MAX_LENGTH = 63;

    public const SERVICE_CODES = [
        'id',
        'fields',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // static
    protected static array $reservedByClass = [];

    /**
     * Field code error regardless of class, or null if the code is valid.
     */
    public static function getError(string $code): ?string
    {
        if (!preg_match(self::PATTERN, $code)) {
            return __('mutable-content::validation.field_code.format', ['code' => $code]);
        }

        if (strlen($code) > self::MAX_LENGTH) {
            return __('mutable-content::validation.field_code.max', ['code' => $code, 'max' => self::MAX_LENGTH]);
        }

        if (in_array($code, self::SERVICE_CODES, true)) {
            return __('mutable-content::validation.field_code.reserved', ['code' => $code]);
        }

        return null;
    }

    /**
     * Field code error for the class, or null if the code is valid.
     *
     * @param class-string<ModelWithFields> $class
     */
    public static function getErrorForClass(string $code, string $class): ?string
    {
        $error = self::getError($code);

        if ($error) {
            return $error;
        }

        if (in_array($code, self::getReservedForClass($class), true)) {
            $classLabel = app(MutableClassRegistry::class)->getKeyValuePairs()[$class] ?? $class;

            return __('mutable-content::validation.field_code.reserved_in_class', ['code' => $code, 'class' => $classLabel]);
        }

        return null;
    }

    /**
     * Reserved columns and relations of the class. Relations are detected by a Relation return type.
     *
     * @param class-string<ModelWithFields> $class
     * @return array<string>
     */
    public static function getReservedForClass(string $class): array
    {
        if (isset(self::$reservedByClass[$class])) {
            return self::$reservedByClass[$class];
        }

        $result = self::SERVICE_CODES;

        if (!class_exists($class)) {
            return $result;
        }

        if (is_subclass_of($class, Model::class)) {
            $model = new $class();

            $result[] = $model->getKeyName();

            if ($model->usesTimestamps()) {
                $result[] = $model->getCreatedAtColumn();
                $result[] = $model->getUpdatedAtColumn();
            }

            if (method_exists($model, 'getDeletedAtColumn')) {
                $result[] = $model->getDeletedAtColumn();
            }
        }

        foreach (new ReflectionClass($class)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_a($type->getName(), Relation::class, true)) {
                $result[] = $method->getName();
            }
        }

        return self::$reservedByClass[$class] = array_values(array_unique(array_filter($result)));
    }
}
