<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Contracts\MutableModel;
use Amarenkov\MutableContent\ValueObjects\Duration;

/**
 * Whole seconds.
 */
class DurationType extends MeasureType
{
    // public
    public function __construct()
    {
        parent::__construct(Duration::class);
    }

    public function normalize(mixed $value, MutableModel $model, string $code): mixed
    {
        return is_numeric($value) ? (int)round((float)$value) : $value;
    }
}
