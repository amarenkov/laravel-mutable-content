<?php

namespace Amarenkov\MutableContent\Attributes\Field;

use Attribute;

use Amarenkov\MutableContent\Domain\Field\Field as DomainField;
use Amarenkov\MutableContent\Domain\Field\Usage as DomainFieldUsage;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Field
{
    public function __construct(
        public string $code,
        public string $type,
        public string $label
    ) {}

    public function toDomainField($class) : DomainField
    {
        $field = new DomainField(
            $this->code,
            $this->type
        )
            ->label(__($this->label));

        $field->addUsage(new DomainFieldUsage($class::getClassScope())
            ->isRequired($this->isRequired)
            ->isImmutable($this->isImmutable)
            ->isImmutableForSystemObjects($this->isImmutableForSystemObjects)
        );

        return $field;
    }

    public $isRequired = false;
    public $isImmutable = false;
    public $isImmutableForSystemObjects = false;
}