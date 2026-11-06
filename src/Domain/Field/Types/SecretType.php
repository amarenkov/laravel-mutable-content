<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Contracts\MutableModel;
use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Helpers\SecretHelper;

/**
 * A text stored encrypted and shown as a mask.
 */
class SecretType extends FieldType
{
    // public
    /**
     * A text is encrypted, the text of the current value keeps the current ciphertext, an encrypted value is kept.
     */
    public function normalize(mixed $value, MutableModel $model, string $code): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if ($value === '') {
            return null;
        }

        if (SecretHelper::isEncrypted($value)) {
            return $value;
        }

        $current = $model->getField($code);

        return SecretHelper::decrypt($current) === $value ? $current : SecretHelper::encrypt($value);
    }

    public function format(Field $field, mixed $value): string
    {
        return (string)SecretHelper::maskStored($field, $value);
    }

    public function settings(): array
    {
        return [
            TypeSettings::VISIBLE_START => range(0, TypeSettings::MAX_VISIBLE),
            TypeSettings::VISIBLE_END => range(0, TypeSettings::MAX_VISIBLE),
        ];
    }

    public function defaultSetting(string $code): mixed
    {
        return in_array($code, [TypeSettings::VISIBLE_START, TypeSettings::VISIBLE_END], true) ? TypeSettings::DEFAULT_VISIBLE : parent::defaultSetting($code);
    }
}
