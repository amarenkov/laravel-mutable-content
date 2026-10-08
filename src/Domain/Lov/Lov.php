<?php

namespace Amarenkov\MutableContent\Domain\Lov;

use InvalidArgumentException;

use Amarenkov\MutableContent\Domain\Field\Field;

class Lov
{
    // const
    
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

    public function items(array $value)
    {
        foreach ($value as $item) {
            $this->addItem($item);
        }

        return $this;
    }

    public function addItem(Item $item)
    {
        $existed = @$this->items[$item->code];

        if ($existed) {
            throw new InvalidArgumentException($item->code.' is already in items.');
        } else {
            $this->items[$item->code] = $item;
        }

        return $this;
    }

    public function absorb(Lov $source)
    {
        $this->items($source->items);
        
        if (!$this->label) {
            $this->label($source->label);
        }
        
        return $this;
    }

    public function itemsToArray()
    {
        $result = [];

        foreach ($this->items as $item) {
            $result[] = $item->toArray();
        }

        return $result;
    }

    public function attributesToArray()
    {
        return array_filter([
            Field::COMMON_CODE_CODE => $this->code,
            Field::COMMON_CODE_LABEL => $this->label
        ]);
    }

    public function toArray()
    {
        $attributes = $this->attributesToArray();

        return array_merge($attributes, $this->itemsToArray());
    }

    public ?string $label = null;

    public array $items = [];
}