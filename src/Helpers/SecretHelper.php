<?php

namespace Amarenkov\MutableContent\Helpers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

/**
 * Values of secret fields: stored encrypted with the application key, shown as a mask with a few characters at the ends.
 */
class SecretHelper
{
    // const
    public const MASK = '********';

    public const MASK_MIDDLE = '****';

    // static
    public static function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    /**
     * Decrypted text, or null if the value is empty or not encrypted with the application key.
     */
    public static function decrypt(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function isEncrypted(mixed $value): bool
    {
        return static::decrypt($value) !== null;
    }

    /**
     * The text with $start and $end characters kept and a middle of a fixed length, so the mask does not tell the length.
     * A text shorter than twice the kept characters is masked entirely.
     */
    public static function mask(?string $text, int $start = TypeSettings::DEFAULT_VISIBLE, int $end = TypeSettings::DEFAULT_VISIBLE): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        $start = max(0, $start);
        $end = max(0, $end);

        if ($start + $end === 0 || mb_strlen($text) < 2 * ($start + $end)) {
            return self::MASK;
        }

        return mb_substr($text, 0, $start).self::MASK_MIDDLE.($end > 0 ? mb_substr($text, -$end) : '');
    }

    /**
     * Mask of a stored value by the visible_start and visible_end settings of the field; a value that cannot be
     * decrypted is masked entirely.
     */
    public static function maskStored(?Field $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = static::decrypt($value);

        if ($text === null || $field === null) {
            return self::MASK;
        }

        return static::mask($text, TypeSettings::visibleStart($field), TypeSettings::visibleEnd($field));
    }
}
