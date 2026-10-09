<?php

namespace Amarenkov\MutableContent\Domain\Log;

/**
 * Changed fields of a log entry: code => [old, new]. Values are compared strictly.
 */
class Changes
{
    // static
    public static function between(?array $old, ?array $new): self
    {
        $old ??= [];
        $new ??= [];

        $items = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $code) {
            $before = $old[$code] ?? null;
            $after = $new[$code] ?? null;

            if ($before !== $after) {
                $items[$code] = [$before, $after];
            }
        }

        return new self($items);
    }

    // public
    /**
     * @param array<string, array{mixed, mixed}> $items
     */
    public function __construct(
        public readonly array $items = []
    ) {
    }

    public function isEmpty(): bool
    {
        return !$this->items;
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
