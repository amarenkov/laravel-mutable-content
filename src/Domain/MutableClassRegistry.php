<?php

namespace Amarenkov\MutableContent\Domain;

use InvalidArgumentException;

use Amarenkov\MutableContent\Domain\Lov\Lov;
use Amarenkov\MutableContent\Domain\Lov\Item as LovItem;

class MutableClassRegistry
{
    // const
    public const LOV_CODE = 'mutable_class';
    public const LOV_LABEL = 'Mutable class';

    // protected
    /**
     * @var array<MutableClassDescription>
     */
    protected array $mcds = [];

    public function __construct(
        
    ) {}

    /**
     * @param class-string $class
     */
    public function add(string $class): static
    {
        $mcd = new MutableClassDescription($class);

        array_walk($this->mcds, function($item) use ($mcd) {
            if ($item->mutableClass == $mcd->mutableClass) {
                throw new InvalidArgumentException("Class {$mcd->mutableClass} already exists.");
            }
            if ($item->label == $mcd->label) {
                throw new InvalidArgumentException("Label {$mcd->label} already exists.");
            }
        });

        $this->mcds[] = $mcd;

        return $this;
    }

    /**
     * @return array<MutableClassDescription>
     */
    public function all(): array
    {
        return array_values($this->mcds);
    }

    public function getSystemFields()
    {
        $result = [];

        foreach ($this->all() as $mcd) {
            foreach ($mcd->mutableClass::getSystemFields() as $field) {
                $existed = @$result[$field->code];

                if ($existed) {
                    $existed->absorb($field);
                } else {
                    $result[$field->code] = $field;
                }
            }
        }

        return $result;
    }

    public function getKeyValuePairs()
    {
        $result = [];

        foreach ($this->all() as $mcd) {
            $result[$mcd->mutableClass] = $mcd->label;
        }

        return $result;
    }

    public function getLov($onlyCode = false)
    {
        $lov = new Lov(self::LOV_CODE);

        if ($onlyCode) {
            return $lov;
        }
        
        $lov->label(__(self::LOV_LABEL));

        foreach ($this->all() as $mcd)
            $lov->addItem(
            new LovItem($mcd->mutableClass)
                ->label($mcd->label)
        );
        
        return $lov;
    }
}