<?php

namespace Amarenkov\MutableContent\Domain\Lov;

use Amarenkov\MutableContent\Domain\Field\Field;

class Item
{
    // const
    public const CODE_ICON = 'icon';

    // public
    public function __construct(
        public string $code
    )
    {
    }

    public function label(?string $value)
    {
        $this->label = $value;

        return $this;
    }

    public function icon(?string $value)
    {
        $this->icon = $value;

        return $this;
    }

    /**
     * Values of the item fields: code => value.
     *
     * @param array<string, mixed> $value
     */
    public function fields(array $value)
    {
        $this->fields = $value;

        return $this;
    }

    public function toArray()
    {
        return array_filter([
            Field::COMMON_CODE_CODE => $this->code,
            Field::COMMON_CODE_LABEL => $this->label,
            self::CODE_ICON => $this->icon,
        ]) + array_filter($this->fields, fn ($value) => $value !== null);
    }

    public ?string $label = null;
    public ?string $icon = null;

    /**
     * @var array<string, mixed>
     */
    public array $fields = [];
}