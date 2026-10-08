<?php

namespace Amarenkov\MutableContent\Domain;

use ReflectionClass;

use InvalidArgumentException;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Models\Lov\Lov as ModelsLov;
use Amarenkov\MutableContent\Models\Lov\Item as ModelsLovItem;

use Amarenkov\MutableContent\Attributes\Class\Code as AttributeClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as AttributeClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as AttributeLovItem;

class LovRegistry
{
    const SOURCE_GET_LOV_METHOD = 'getLov';

    /**
     * @var array<class-string|Lov|object>
     */
    protected array $sources = [];

    protected ?array $lovs = null;
    protected ?array $lovCodesById = null;
    protected array $lovItems = [];
    protected array $lovItemIcons = [];
    protected array $itemIcons = [];

    protected function getLovFromClass($class)
    {
        $reflection = new ReflectionClass($class);

        $code = null;
        $label = null;
        $items = [];

        foreach ($reflection->getAttributes() as $attribute) {
            if ($attribute->getName() == AttributeClassCode::class) {
                $code = $attribute->newInstance()->code;
            } else if ($attribute->getName() == AttributeClassLabel::class) {
                $label = __($attribute->newInstance()->label);
            } else if ($attribute->getName() == AttributeLovItem::class) {
                $items[] = $attribute->newInstance()->toDomainLovItem();
            }
        }

        $lov = (is_subclass_of($class, Lov::class) ? new $class($code) : new Lov($code))
            ->label($label)
            ->items($items);

        return $lov;
    }

    protected function getLovFromSource($source)
    {
        if (is_string($source)) {
            $lov = $this->getLovFromClass($source);
        } else {
            $lov = $source->getLov();
        }

        foreach ($lov->items as $item) {
            if ($item->icon === null && isset($this->itemIcons[$lov->code][$item->code])) {
                $item->icon($this->itemIcons[$lov->code][$item->code]);
            }
        }

        return $lov;
    }

    // public
    public function initLovsList()
    {
        if ($this->lovs === null) {
            $this->lovs = ModelsLov::orderBy('label')->pluck('label', 'code')->toArray();
        }
    }

    public function initLov($lovCode)
    {
        if (@$this->lovItems[$lovCode] === null) {
            $this->lovItems[$lovCode] = [];
            $this->lovItemIcons[$lovCode] = [];

            if (@$this->sources[$lovCode]) {
                $lov = $this->getLovFromSource($this->sources[$lovCode]);

                foreach ($lov->items as $item) {
                    $this->lovItems[$lovCode][$item->code] = $item->label;

                    if ($item->icon !== null) {
                        $this->lovItemIcons[$lovCode][$item->code] = $item->icon;
                    }
                }
            }

            $items = [];

            foreach (ModelsLovItem::whereHas('lov', function ($query) use ($lovCode) {
                $query->where('code', $lovCode);
            })->get() as $itemModel) {
                $code = $itemModel->{Field::COMMON_CODE_CODE};

                $items[$code] = $itemModel->{Field::COMMON_CODE_LABEL};

                if ((string)$itemModel->{ModelsLovItem::CODE_ICON} !== '') {
                    $this->lovItemIcons[$lovCode][$code] = $itemModel->{ModelsLovItem::CODE_ICON};
                }
            }

            $this->lovItems[$lovCode] = array_replace($this->lovItems[$lovCode], $items);

            asort($this->lovItems[$lovCode]);
        }
    }

    /**
     * Reset cached LOVs; they are reloaded from the DB on next access.
     */
    public function flush(): void
    {
        $this->lovs = null;
        $this->lovCodesById = null;
        $this->lovItems = [];
        $this->lovItemIcons = [];
    }

    public function getLovLabel($code)
    {
        $this->initLovsList();

        return @$this->lovs[$code];
    }

    public function getLovCodeById($id): ?string
    {
        if ($this->lovCodesById === null) {
            $this->lovCodesById = ModelsLov::pluck('code', 'id')->toArray();
        }

        $code = $this->lovCodesById[$id] ?? null;

        return $code === null ? null : (string)$code;
    }

    public function getLovsOptions()
    {
        $this->initLovsList();

        return @$this->lovs;
    }

    public function getLovItemLabel($lovCode, $code)
    {
        $this->initLov($lovCode);

        return @$this->lovItems[$lovCode][$code];
    }

    public function getLovItemIcon($lovCode, $code): ?string
    {
        $this->initLov($lovCode);

        return $this->lovItemIcons[$lovCode][$code] ?? null;
    }

    public function getLovItemsOptions($lovCode)
    {
        $this->initLov($lovCode);

        return $this->lovItems[$lovCode] ?? [];
    }

    public function hasLovItem($lovCode, mixed $code): bool
    {
        return (is_string($code) || is_int($code)) && array_key_exists($code, $this->getLovItemsOptions($lovCode));
    }

    /**
     * LOVs described by LOV model subclasses: code => class.
     *
     * @return array<string, class-string<ModelsLov>>
     */
    public function getLovModelClasses(): array
    {
        $result = [];

        foreach ($this->sources as $code => $source) {
            if (is_string($source) && is_subclass_of($source, ModelsLov::class)) {
                $result[(string)$code] = $source;
            }
        }

        return $result;
    }

    /**
     * Register a Domain\Lov\Lov or Models\Lov\Lov subclass with #[ClassCode], #[ClassLabel] and optional #[LovItem].
     *
     * @param class-string $class
     */
    public function addClass(string $class): static
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class {$class} does not exist.");
        }

        $reflection = new ReflectionClass($class);

        $code = null;
        foreach ($reflection->getAttributes(AttributeClassCode::class) as $attribute) {
            $code = $attribute->newInstance()->code;
        }

        if (@$this->sources[$code]) {
            throw new InvalidArgumentException("Lov {$code} already exists.");
        }

        $this->sources[$code] = $class;

        return $this;
    }

    /**
     * Icons for code-defined LOV items that have none. Seeded by LovsSeeder; icons set in the admin are kept.
     *
     * @param array<string, string> $icons item code => icon
     */
    public function addItemIcons(string $lovCode, array $icons): static
    {
        $this->itemIcons[$lovCode] = array_replace($this->itemIcons[$lovCode] ?? [], $icons);

        unset($this->lovItems[$lovCode], $this->lovItemIcons[$lovCode]);

        return $this;
    }

    public function addSource(object $source): static
    {
        if (!method_exists($source, self::SOURCE_GET_LOV_METHOD)) {
            throw new InvalidArgumentException(get_class($source).' does not have '.self::SOURCE_GET_LOV_METHOD.' method.');
        }

        $lov = $source->{self::SOURCE_GET_LOV_METHOD}(true);

        if (@$this->sources[$lov->code]) {
            throw new InvalidArgumentException("Lov {$lov->code} already exists.");
        }

        $this->sources[$lov->code] = $source;

        return $this;
    }

    public function getSystemLovs()
    {
        $result = [];

        foreach ($this->sources as $code => $source) {
            $result[$code] = $this->getLovFromSource($source);
        }

        return $result;
    }
}