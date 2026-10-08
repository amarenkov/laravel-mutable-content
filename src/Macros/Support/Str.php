<?php

namespace Amarenkov\MutableContent\Macros\Support;

use Illuminate\Support\Str as BaseStr;

class Str
{
    // const
    const CODE_MAX_LENGTH = 255;

    const CODE_FALLBACK = 'item';

    // public
    public static function add()
    {
        /**
         * Build a code from a label: transliterated, lowercased, other characters replaced with underscores.
         */
        BaseStr::macro('toCode', function (string $label, int $maxLength = Str::CODE_MAX_LENGTH): string {
            $code = mb_strtolower(BaseStr::transliterate($label));

            $code = preg_replace('/[^a-z0-9]+/', '_', $code);
            $code = trim($code, '_');

            if ($code === '') {
                $code = Str::CODE_FALLBACK;
            }

            if (mb_strlen($code) > $maxLength) {
                $code = trim(mb_substr($code, 0, $maxLength), '_');
            }

            return $code;
        });

        /**
         * Make the code unique by appending _2, _3 and so on.
         *
         * @param array<string> $busyCodes
         */
        BaseStr::macro('uniqueCode', function (string $code, array $busyCodes, int $maxLength = Str::CODE_MAX_LENGTH): string {
            $busyCodes = array_map('strval', $busyCodes);

            if (!in_array($code, $busyCodes, true)) {
                return $code;
            }

            $number = 2;

            do {
                $suffix = '_'.$number;

                $candidate = mb_strlen($code) + mb_strlen($suffix) > $maxLength ?
                    trim(mb_substr($code, 0, $maxLength - mb_strlen($suffix)), '_').$suffix :
                    $code.$suffix;

                $number++;
            } while (in_array($candidate, $busyCodes, true));

            return $candidate;
        });
    }
}
