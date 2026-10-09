<?php

namespace Amarenkov\MutableContent\Domain\Log;

/**
 * Data of a change log entry: author, comment and any other keys.
 */
class LogData
{
    // const
    public const USER_ID = 'user_id';
    public const COMMENT = 'comment';
    public const EVENT = 'event';

    // protected
    protected array $data = [];

    // public
    public function user(int|string|null $userId): static
    {
        return $this->set(self::USER_ID, $userId);
    }

    public function comment(?string $comment): static
    {
        return $this->set(self::COMMENT, $comment);
    }

    /**
     * Merge data over the data given before by top-level keys; a null value removes the key.
     */
    public function data(array $data): static
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function set(string $key, mixed $value): static
    {
        if ($value === null) {
            unset($this->data[$key]);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
