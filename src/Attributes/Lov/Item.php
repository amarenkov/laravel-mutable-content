<?php

namespace Amarenkov\MutableContent\Attributes\Lov;

use Attribute;

use Amarenkov\MutableContent\Domain\Lov\Item as DomainLovItem;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Item
{
    /**
     * @param ?string $icon icon name, as in an icon field
     * @param array<string, mixed> $fields values of the item fields (#[ItemField] of the LOV), seeded while empty
     */
    public function __construct(
        public string $code,
        public ?string $label = null,
        public ?string $icon = null,
        public array $fields = []
    ) {}

    public function toDomainLovItem() : DomainLovItem
    {
        return new DomainLovItem($this->code)
            ->label($this->label === null ? null : __($this->label))
            ->icon($this->icon)
            ->fields($this->fields);
    }
}