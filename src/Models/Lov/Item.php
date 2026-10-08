<?php

namespace Amarenkov\MutableContent\Models\Lov;

use LogicException;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Amarenkov\MutableContent\Domain\Field\Field as DomainField;
use Amarenkov\MutableContent\Domain\Lov\Item as DomainLovItem;
use Amarenkov\MutableContent\Domain\Field\Lov\Type as DomainLovFieldType;

use Amarenkov\MutableContent\Domain\LovRegistry;

use Amarenkov\MutableContent\Models\ModelWithFields;
use Amarenkov\MutableContent\Models\Field\Usage;

use Amarenkov\MutableContent\Attributes\Class\Label as AttributeClassLabel;

use Amarenkov\MutableContent\Attributes\Field\Common\Code as AttributeFieldCode;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as AttributeFieldLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Description as AttributeFieldDescription;
use Amarenkov\MutableContent\Attributes\Field\Common\IsSystem as AttributeFieldIsSystem;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\ObjectClass as CFAObjectClass;

#[Table('lov_items')]
#[Fillable(['id', 'fields'])]

#[AttributeClassLabel('LOV item')]

#[AttributeFieldCode()]
#[AttributeFieldLabel()]
#[AttributeFieldDescription()]
#[AttributeFieldIsSystem()]
class Item extends ModelWithFields
{
    // const
    #[CFAType(DomainLovFieldType::TYPE_OBJECT), CFALabel('LOV'), CFAIsImmutable, CFAObjectClass(Lov::class)]
    const FIELD_LOV_ID = 'lov_id';

    #[CFAType(DomainLovFieldType::TYPE_ICON), CFALabel('Icon')]
    const CODE_ICON = DomainLovItem::CODE_ICON;

    // static
    protected static array|bool|null $fieldDefinitions = null;

    public static function uniqueFieldSets(): array
    {
        return [[self::FIELD_LOV_ID, DomainField::COMMON_CODE_CODE]];
    }

    public static function getSystemFields()
    {
        $result = parent::getSystemFields();

        $own = array_keys($result);

        foreach (app(LovRegistry::class)->getLovModelClasses() as $lovClass) {
            foreach ($lovClass::getItemSystemFields() as $field) {
                $existed = $result[$field->code] ?? null;

                if (!$existed) {
                    $result[$field->code] = $field;

                    continue;
                }

                if (in_array($field->code, $own, true)) {
                    throw new LogicException($field->code.' item field of LOV '.$lovClass.' is already in class '.static::class.' fields list');
                }

                if ($existed->fieldType !== $field->fieldType || $existed->lovCode !== $field->lovCode || $existed->objectClass !== $field->objectClass) {
                    throw new LogicException($field->code.' item field of LOV '.$lovClass.' differs from the same field of another LOV');
                }

                $existed->absorb($field);
            }
        }

        return $result;
    }

    /**
     * Field scopes of the LOV items: class scopes plus the LOV scope.
     *
     * @return array<string>
     */
    public static function getFieldScopesForLov(?string $lovCode): array
    {
        $scopes = static::getFieldScopes();

        if ($lovCode !== null && $lovCode !== '') {
            $scopes[] = Usage::makeScope(Usage::CODE_LOV_CODE, $lovCode);
        }

        return $scopes;
    }

    protected static function booted(): void
    {
        foreach (['saved', 'deleted', 'restored'] as $event) {
            static::$event(function () {
                app(LovRegistry::class)->flush();
            });
        }
    }

    // public
    public function fieldScopes(): array
    {
        $lovId = $this->getField(self::FIELD_LOV_ID);

        return static::getFieldScopesForLov($lovId === null ? null : app(LovRegistry::class)->getLovCodeById($lovId));
    }

    public function lov(): BelongsTo
    {
        return $this->belongsTo(Lov::class, self::FIELD_LOV_ID);
    }

    public function setSystemFields(DomainLovItem $lovItem)
    {
        $this->{DomainField::COMMON_CODE_IS_SYSTEM} = true;

        if ($lovItem->icon !== null && (string)$this->{self::CODE_ICON} === '') {
            $this->{self::CODE_ICON} = $lovItem->icon;
        }
    }
}
