<?php

namespace Amarenkov\MutableContent\Helpers;

use Amarenkov\MutableContent\Domain\Field\Field;

use Amarenkov\MutableContent\Models\Field\Usage as UsageModel;

/**
 * Field definitions of all models with fields, kept per process and shared by the classes using the trait.
 */
class FieldDefinitionCache
{
    // static
    /**
     * @var array<class-string, array<string, array<string, Field>>>
     */
    public static array $definitions = [];

    /**
     * @var array<class-string, array<string, string>>
     */
    public static array $extractedFields = [];

    /**
     * @var array<class-string, class-string>
     */
    public static array $cachedWhileLoading = [];

    /**
     * @var ?array<Field>
     */
    protected static ?array $usageFields = null;

    protected static bool $usageFieldsLoading = false;

    public static function flush(): void
    {
        self::$definitions = [];

        self::$usageFields = null;
    }

    /**
     * Fields of the usages bound to the scopes, ordered by usage id; null while the usages are being loaded.
     * The usages of all scopes are loaded by one query and kept until flush().
     * Definitions built while loading have system fields only and are dropped once the usages are loaded.
     *
     * @param array<string> $scopes
     * @return ?array<Field>
     */
    public static function usageFields(array $scopes): ?array
    {
        if (self::$usageFields === null) {
            if (self::$usageFieldsLoading) {
                return null;
            }

            self::$usageFieldsLoading = true;

            try {
                $usageFields = [];

                foreach (UsageModel::whereHas('field')->with('field')->orderBy('id')->get() as $usageModel) {
                    $usageFields[] = $usageModel->toDomainField();
                }

                self::$usageFields = $usageFields;
            } finally {
                self::$usageFieldsLoading = false;

                foreach (self::$cachedWhileLoading as $class) {
                    unset(self::$definitions[$class]);
                }

                self::$cachedWhileLoading = [];
            }
        }

        $scopes = array_flip($scopes);
        $result = [];

        foreach (self::$usageFields as $usageField) {
            if (isset($scopes[array_key_first($usageField->usages)])) {
                $field = clone $usageField;
                $field->usages = array_map(fn ($usage) => clone $usage, $field->usages);

                $result[] = $field;
            }
        }

        return $result;
    }
}
