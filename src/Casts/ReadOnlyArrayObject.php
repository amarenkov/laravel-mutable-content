<?php

namespace Amarenkov\MutableContent\Casts;

use LogicException;

use Illuminate\Database\Eloquent\Casts\ArrayObject;

/**
 * Read-only view of the fields column. Change fields with setField(), fill() or mergeWithFields().
 */
class ReadOnlyArrayObject extends ArrayObject
{
    public function offsetGet(mixed $key): mixed
    {
        return parent::offsetGet($key);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->denyWrite();
    }

    public function offsetUnset(mixed $key): void
    {
        $this->denyWrite();
    }

    public function append(mixed $value): void
    {
        $this->denyWrite();
    }

    public function exchangeArray(array|object $array): array
    {
        $this->denyWrite();
    }

    public function asort(int $flags = SORT_REGULAR): true
    {
        $this->denyWrite();
    }

    public function ksort(int $flags = SORT_REGULAR): true
    {
        $this->denyWrite();
    }

    public function uasort(callable $callback): true
    {
        $this->denyWrite();
    }

    public function uksort(callable $callback): true
    {
        $this->denyWrite();
    }

    public function natsort(): true
    {
        $this->denyWrite();
    }

    public function natcasesort(): true
    {
        $this->denyWrite();
    }

    protected function denyWrite(): never
    {
        throw new LogicException('Fields are read-only, use setField(), fill() or mergeWithFields()');
    }
}
