<?php

namespace Amarenkov\MutableContent\Contracts;

use Illuminate\Support\Carbon;

use Amarenkov\MutableContent\Domain\Field\Field;

use Amarenkov\MutableContent\Models\Field\Usage;

use Amarenkov\MutableContent\ValueObjects\Money;

/**
 * Model with fields, implemented by the HasMutableFields trait.
 */
interface MutableModel
{
    // static
    public static function fieldUsageScope(Usage $usage): string;

    public static function fillFieldUsageFromScope(Usage $usage, string $scope): void;

    /**
     * @return array<string>
     */
    public static function getFieldScopes(): array;

    public static function getClassScope(): string;

    public static function typeField(): ?string;

    public static function typeCodeOf(mixed $value): ?string;

    /**
     * @return array<string, string>
     */
    public static function getTypeOptions(): array;

    public static function getTypeScope(string $typeCode): string;

    /**
     * @return array<string>
     */
    public static function getFieldScopesForType(?string $typeCode): array;

    /**
     * @return array<array<string>>
     */
    public static function uniqueFieldSets(): array;

    /**
     * @return array<string, string>
     */
    public static function getExtractedFields(): array;

    /**
     * @return array<string, int>
     */
    public static function getFieldMaxLengths(): array;

    public static function toDateTimeValue(mixed $value): mixed;

    public static function toMoneyValue(mixed $value): mixed;

    public static function getSystemFields();

    /**
     * @param ?array<string> $scopes
     * @return array<string, Field>
     */
    public static function getFieldDefinitions(?array $scopes = null);

    // public
    public function getKey();

    /**
     * @return array<string>
     */
    public function fieldScopes(): array;

    public function typeCode(): ?string;

    /**
     * @return array<string, Field>
     */
    public function fieldDefinitions(): array;

    public function setField($name, $value);

    public function getField($name, $default = null);

    public function mergeWithFields($data);

    public function getMoney($name): ?Money;

    public function getSecret($name): ?string;

    public function maskedSecret($name): ?string;

    public function getDateTime($name): ?Carbon;

    public function isSystem();

    public function code();

    public function label();
}
