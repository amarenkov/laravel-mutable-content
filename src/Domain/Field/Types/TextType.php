<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Domain\Field\TypeSettings;

/**
 * Multiline text, plain or Markdown.
 */
class TextType extends FieldType
{
    // public
    public function settings(): array
    {
        return [TypeSettings::FORMAT => TypeSettings::FORMATS];
    }

    public function defaultSetting(string $code): mixed
    {
        return $code === TypeSettings::FORMAT ? TypeSettings::FORMAT_PLAIN : parent::defaultSetting($code);
    }
}
