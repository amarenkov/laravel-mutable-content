<?php

namespace Amarenkov\MutableContent\Attributes\FieldAttr;

use Attribute;

use ReflectionClassConstant;

use Amarenkov\MutableContent\Domain\Field\Field as DomainField;
use Amarenkov\MutableContent\Domain\Field\Usage as DomainFieldUsage;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
class FieldAttr
{
    // static
    /**
     * FieldAttr attribute values on a constant: code => value.
     */
    public static function collect(ReflectionClassConstant $reflectionConstant): array
    {
        $attrs = [];

        foreach ($reflectionConstant->getAttributes() as $attribute) {
            if (strpos($attribute->getName(), 'Amarenkov\\MutableContent\\Attributes\\FieldAttr\\') === 0) {
                $attr = $attribute->newInstance();

                $attrs[$attr->code] = $attr->value;
            }
        }

        return $attrs;
    }

    /**
     * @param ?string $scope usage scope; the class itself by default
     */
    public static function buildDomainField($class, $name, $attrs, ?string $scope = null) : DomainField
    {
        $field = new DomainField(
            $name,
            $attrs[DomainField::CODE_FIELD_TYPE]
        )
            ->label(__($attrs[DomainField::COMMON_CODE_LABEL]))
            ->lovCode(@$attrs[DomainField::CODE_LOV_CODE])
            ->objectClass($attrs[DomainField::CODE_OBJECT_CLASS] ?? null)
            ->typeSettings($attrs[DomainField::CODE_FIELD_TYPE_SETTINGS] ?? []);
        
        $field->addUsage(new DomainFieldUsage($scope ?? $class::getClassScope())
            ->isRequired($attrs[DomainFieldUsage::CODE_IS_REQUIRED] ?? false)
            ->isImmutable($attrs[DomainFieldUsage::CODE_IS_IMMUTABLE] ?? false)
            ->isImmutableForSystemObjects($attrs[DomainFieldUsage::CODE_IS_IMMUTABLE_FOR_SYSTEM_OBJECTS] ?? false)
        );

        return $field;
    }

    // public
    public function __construct(
        public string $code,
        public $value
    ) {}
}