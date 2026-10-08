<?php

namespace Amarenkov\MutableContent\Attributes\Lov;

use Attribute;

use Amarenkov\MutableContent\Domain\Lov\Item as DomainLovItem;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Item
{
    /**
     * @param ?string $icon icon name, as in an icon field
     */
    public function __construct(
        public string $code,
        public ?string $label = null,
        public ?string $icon = null
    ) {}

    public function toDomainLovItem() : DomainLovItem
    {
        return new DomainLovItem($this->code)
            ->label($this->label === null ? null : __($this->label))
            ->icon($this->icon);
    }
}