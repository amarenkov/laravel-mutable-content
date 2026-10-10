<?php

namespace Amarenkov\MutableContent\Models\Lov;

use LogicException;

use ReflectionClass;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use Amarenkov\MutableContent\Domain\Field\Field as DomainField;
use Amarenkov\MutableContent\Domain\Lov\Lov as DomainLov;
use Amarenkov\MutableContent\Domain\Field\Lov\Type as DomainLovFieldType;

use Amarenkov\MutableContent\Domain\LovRegistry;

use Amarenkov\MutableContent\Helpers\FieldCodeHelper;

use Amarenkov\MutableContent\Models\ModelWithFields;
use Amarenkov\MutableContent\Models\Field\Field;
use Amarenkov\MutableContent\Models\Field\Usage;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\FieldAttr;
use Amarenkov\MutableContent\Attributes\Lov\ItemField as AttributeItemField;
use Amarenkov\MutableContent\Attributes\Field\Common\Code as CommonFieldCode;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as CommonFieldLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Description as CommonFieldDescription;
use Amarenkov\MutableContent\Attributes\Field\Common\IsSystem as CommonFieldIsSystem;

#[Table('lovs')]

#[ClassLabel('LOV')]

#[CommonFieldCode()]
#[CommonFieldLabel()]
#[CommonFieldDescription()]
#[CommonFieldIsSystem()]
class Lov extends ModelWithFields
{
    // const
    
    // static
    protected static array|bool|null $fieldDefinitions = null;

    public static function uniqueFieldSets(): array
    {
        return [[DomainField::COMMON_CODE_CODE]];
    }

    public static function getClassScope(): string
    {
        return Usage::makeScope(Usage::CODE_MUTABLE_CLASS, self::class);
    }

    public static function getSystemFields()
    {
        return static::class === self::class ? parent::getSystemFields() : Lov::getSystemFields();
    }

    /**
     * LOV code of the subclass (#[ClassCode]); null for Lov itself.
     */
    public static function lovCode(): ?string
    {
        if (static::class === self::class) {
            return null;
        }

        $attributes = new ReflectionClass(static::class)->getAttributes(ClassCode::class);

        if (!$attributes) {
            throw new LogicException(static::class.' must have #[ClassCode] attribute');
        }

        return $attributes[0]->newInstance()->code;
    }

    public static function instance(): ?static
    {
        return static::query()->first();
    }

    /**
     * System fields of the LOV items declared with #[ItemField].
     *
     * @return array<string, DomainField>
     */
    public static function getItemSystemFields(): array
    {
        $lovCode = static::lovCode();

        if ($lovCode === null) {
            return [];
        }

        $scope = Item::getTypeScope($lovCode);

        $result = [];

        foreach (new ReflectionClass(static::class)->getReflectionConstants() as $reflectionConstant) {
            if (!$reflectionConstant->getAttributes(AttributeItemField::class)) {
                continue;
            }

            $field = FieldAttr::buildDomainField(Item::class, $reflectionConstant->getValue(), FieldAttr::collect($reflectionConstant), $scope);

            if (isset($result[$field->code])) {
                throw new LogicException($field->code.' item field is already in LOV '.static::class);
            }

            if ($error = FieldCodeHelper::getErrorForClass($field->code, Item::class)) {
                throw new LogicException($error);
            }

            $result[$field->code] = $field;
        }

        return $result;
    }

    protected static function booted(): void
    {
        if (static::class !== self::class) {
            static::addGlobalScope('lov_code', function (Builder $query) {
                $query->where(DomainField::COMMON_CODE_CODE, static::lovCode());
            });
        }

        static::saving(function (Lov $lov) {
            $originalCode = $lov->originalCode();

            if ($originalCode !== null && $originalCode !== $lov->code() && $lov->isUsedInFields($originalCode)) {
                throw new LogicException($lov->usedInFieldsMessage($originalCode, 'code_used_in_fields'));
            }
        });

        static::deleting(function (Lov $lov) {
            if ($lov->isUsedInFields()) {
                throw new LogicException($lov->usedInFieldsMessage());
            }
        });

        foreach (['saved', 'deleted', 'restored'] as $event) {
            static::$event(function () {
                app(LovRegistry::class)->flush();
            });
        }
    }
    
    // public
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, Item::FIELD_LOV_ID);
    }

    public function usingFieldsQuery(?string $code = null): Builder
    {
        $code ??= $this->code();

        return Field::query()->where(function (Builder $query) use ($code) {
            $query->where('fields->'.DomainField::CODE_LOV_CODE, $code)
                ->orWhereHas('usages', function (Builder $query) use ($code) {
                    $query->where(Usage::CODE_SCOPE, Item::getTypeScope($code));
                });
        });
    }

    public function isUsedInFields(?string $code = null): bool
    {
        return $this->usingFieldsQuery($code)->exists();
    }

    public function originalCode(): ?string
    {
        return $this->exists ? ($this->getOriginal('fields')[DomainField::COMMON_CODE_CODE] ?? null) : null;
    }

    public function usedInFieldsMessage(?string $code = null, string $key = 'used_in_fields'): string
    {
        $fields = $this->usingFieldsQuery($code)
            ->orderBy(DomainField::COMMON_CODE_CODE)
            ->get()
            ->map(fn (Field $field) => $field->label() ?: $field->code())
            ->implode(', ');

        return __('mutable-content::lov.'.$key, ['lov' => $this->label() ?: $this->code(), 'fields' => $fields]);
    }

    public function setSystemFields(DomainLov $lov)
    {
        $this->{DomainField::COMMON_CODE_IS_SYSTEM} = true;
    }

    /**
     * Create LOV items from labels. Existing labels are skipped, soft-deleted items restored,
     * codes of new ones transliterated from labels.
     *
     * @param array<string> $labels
     * @return array{created: array<Item>, restored: array<Item>, skipped: array<string>}
     */
    public function createItemsFromLabels(array $labels, $comment = null, $userId = null): array
    {
        $existedLabels = [];
        foreach ($this->items()->pluck(DomainField::COMMON_CODE_LABEL) as $existedLabel) {
            $existedLabels[mb_strtolower(trim((string)$existedLabel))] = true;
        }

        $trashedItems = [];
        foreach ($this->items()->onlyTrashed()->orderBy('deleted_at')->orderBy('id')->get() as $trashedItem) {
            $trashedItems[mb_strtolower(trim($trashedItem->label()))] = $trashedItem;
        }

        $busyCodes = $this->items()->withTrashed()->pluck(DomainField::COMMON_CODE_CODE)->all();

        $created = [];
        $restored = [];
        $skipped = [];

        DB::transaction(function () use ($labels, $comment, $userId, &$existedLabels, &$trashedItems, &$busyCodes, &$created, &$restored, &$skipped) {
            foreach ($labels as $label) {
                $label = trim((string)$label);

                if ($label === '') {
                    continue;
                }

                $key = mb_strtolower($label);

                if (isset($existedLabels[$key])) {
                    $skipped[] = $label;

                    continue;
                }

                $existedLabels[$key] = true;

                $trashedItem = $trashedItems[$key] ?? null;

                if ($trashedItem) {
                    unset($trashedItems[$key]);

                    $trashedItem->withLogContext($comment)->user($userId);
                    $trashedItem->restore();

                    $restored[] = $trashedItem;

                    continue;
                }

                $code = Str::uniqueCode(Str::toCode($label), $busyCodes);
                $busyCodes[] = $code;

                $item = new Item();
                $item->{Item::FIELD_LOV_ID} = $this->id;
                $item->{DomainField::COMMON_CODE_CODE} = $code;
                $item->{DomainField::COMMON_CODE_LABEL} = $label;
                $item->withLogContext($comment)->user($userId);
                $item->save();

                $created[] = $item;
            }
        });

        return [
            'created' => $created,
            'restored' => $restored,
            'skipped' => $skipped,
        ];
    }
}
