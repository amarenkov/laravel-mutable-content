<?php

namespace Amarenkov\MutableContent\Tests\Feature\Lovs;

use Amarenkov\MutableContent\Database\Seeders\FieldsSeeder;
use Amarenkov\MutableContent\Database\Seeders\LovsSeeder;
use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Domain\MutableClassRegistry;
use Amarenkov\MutableContent\Models\Field\Field;
use Amarenkov\MutableContent\Models\Lov\Item;
use Amarenkov\MutableContent\Models\Lov\Lov;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Lovs\RecordStatus;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class LovTest extends FeatureTestCase
{
    protected function registry(): LovRegistry
    {
        return $this->app->make(LovRegistry::class);
    }

    protected function statusLov(): Lov
    {
        return Lov::where('code', RecordStatus::CODE)->first();
    }

    public function test_code_lovs_are_seeded(): void
    {
        $lov = $this->statusLov();

        $this->assertTrue($lov->isSystem());
        $this->assertSame('Record status', $lov->label);
        $this->assertSame(['active', 'draft'], $lov->items()->orderBy('code')->pluck('code')->all());
        $this->assertTrue($lov->items()->first()->isSystem());
    }

    public function test_seeders_are_idempotent(): void
    {
        $counts = fn () => [Lov::count(), Item::count(), Field::count()];
        $before = $counts();

        $this->seed(LovsSeeder::class);
        $this->seed(FieldsSeeder::class);

        $this->assertSame($before, $counts());
    }

    public function test_registry_merges_code_and_database_items(): void
    {
        $item = new Item();
        $item->fill([Item::FIELD_LOV_ID => $this->statusLov()->id, 'code' => 'archived', 'label' => 'Archived']);
        $item->save();

        $registry = $this->registry();

        $this->assertSame(['active' => 'Active', 'archived' => 'Archived', 'draft' => 'Draft'], $registry->getLovItemsOptions(RecordStatus::CODE));
        $this->assertSame('Record status', $registry->getLovLabel(RecordStatus::CODE));
        $this->assertTrue($registry->hasLovItem(RecordStatus::CODE, 'archived'));
        $this->assertFalse($registry->hasLovItem(RecordStatus::CODE, 'unknown'));
        $this->assertFalse($registry->hasLovItem(RecordStatus::CODE, ['draft']));
    }

    public function test_database_label_overrides_code_label(): void
    {
        $item = $this->statusLov()->items()->where('code', 'draft')->first();
        $item->label = 'Initial';
        $item->save();

        $this->assertSame('Initial', $this->registry()->getLovItemLabel(RecordStatus::CODE, 'draft'));
    }

    public function test_mutable_classes_are_a_lov(): void
    {
        $options = $this->registry()->getLovItemsOptions(MutableClassRegistry::LOV_CODE);

        $this->assertSame('Record', $options[Record::class]);
        $this->assertSame('Field', $options[Field::class]);
    }

    public function test_field_types_lov_is_translated(): void
    {
        $this->assertSame('Weight', $this->registry()->getLovItemLabel(Type::CLASS_CODE, Type::TYPE_WEIGHT));
    }

    public function test_item_icons_added_from_outside(): void
    {
        $registry = $this->registry();
        $registry->addItemIcons(RecordStatus::CODE, ['active' => 'o-check']);

        $this->assertSame('o-check', $registry->getLovItemIcon(RecordStatus::CODE, 'active'));
        $this->assertNull($registry->getLovItemIcon(RecordStatus::CODE, 'draft'));
    }

    public function test_create_items_from_labels(): void
    {
        $lov = new Lov();
        $lov->fill(['code' => 'colors', 'label' => 'Colors']);
        $lov->save();

        $first = $lov->createItemsFromLabels(['Red', 'Green', '', 'Тёмно-синий']);

        $this->assertCount(3, $first['created']);
        $this->assertSame(['green', 'red', 'tiomno_sinii'], $lov->items()->orderBy('code')->pluck('code')->all());

        $lov->items()->where('code', 'green')->first()->delete();

        $second = $lov->createItemsFromLabels([' red ', 'GREEN', 'Blue', 'blue']);

        $this->assertSame(['red', 'blue'], $second['skipped']);
        $this->assertSame(['green'], array_map(fn (Item $item) => $item->code, $second['restored']));
        $this->assertSame(['blue'], array_map(fn (Item $item) => $item->code, $second['created']));
        $this->assertSame(4, $lov->items()->count());
    }

    public function test_lov_usage_in_fields_is_detected(): void
    {
        $lov = $this->statusLov();

        $this->assertTrue($lov->isUsedInFields());
        $this->assertSame('The "Record status" LOV is used in fields: Status. Remove it from these fields first.', $lov->usedInFieldsMessage());

        $other = new Lov();
        $other->fill(['code' => 'sizes', 'label' => 'Sizes']);
        $other->save();

        $this->assertFalse($other->isUsedInFields());
    }
}
