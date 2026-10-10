<?php

namespace Amarenkov\MutableContent\Tests\Feature\Definitions;

use Illuminate\Support\Facades\Artisan;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Helpers\DefinitionsHelper;
use Amarenkov\MutableContent\Helpers\LogHelper;
use Amarenkov\MutableContent\Models\Field\Field;
use Amarenkov\MutableContent\Models\Field\Usage;
use Amarenkov\MutableContent\Models\Lov\Item;
use Amarenkov\MutableContent\Models\Lov\Lov;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Lovs\RecordStatus;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class DefinitionsTest extends FeatureTestCase
{
    protected function createDefinitions(): void
    {
        $lov = new Lov();
        $lov->mergeWithFields(['code' => 'size', 'label' => 'Size']);
        $lov->save();

        $lov->createItemsFromLabels(['Small', 'Large']);

        $statusId = Lov::query()->where('code', RecordStatus::CODE)->value('id');
        $item = new Item();
        $item->mergeWithFields([Item::FIELD_LOV_ID => $statusId, 'code' => 'archived', 'label' => 'Archived']);
        $item->save();

        $field = new Field();
        $field->fill(['code' => 'size', 'field_type' => Type::TYPE_LOV_ITEM, 'lov_code' => 'size', 'label' => 'Size']);
        $field->save();

        $usage = new Usage();
        $usage->fill([Usage::FIELD_FIELD_ID => $field->id, Usage::CODE_MUTABLE_CLASS => Record::class, Usage::CODE_IS_REQUIRED => true]);
        $usage->save();
    }

    protected function deleteDefinitions(): void
    {
        foreach ([Usage::class, Field::class, Item::class, Lov::class] as $class) {
            foreach ($class::query()->get() as $record) {
                if (!$record->isSystem()) {
                    $record->forceDelete();
                }
            }
        }
    }

    public function test_export_contains_only_records_created_in_the_admin_panel(): void
    {
        $this->createDefinitions();

        $data = DefinitionsHelper::export();

        $this->assertSame(['size'], array_column($data['lovs'], 'code'));
        $this->assertEqualsCanonicalizing([['size', 'small'], ['size', 'large'], [RecordStatus::CODE, 'archived']], array_map(fn ($item) => [$item['lov'], $item['code']], $data['items']));
        $this->assertSame(['size'], array_column($data['fields'], 'code'));
        $this->assertSame([['size', true]], array_map(fn ($usage) => [$usage['field'], $usage['is_required']], $data['usages']));
        $this->assertArrayNotHasKey('lov_id', $data['items'][0]);
    }

    public function test_import_recreates_definitions_by_codes(): void
    {
        $this->createDefinitions();
        $data = DefinitionsHelper::export();
        $this->deleteDefinitions();

        $result = DefinitionsHelper::import($data, 'Moved from staging');

        $this->assertSame(['lovs' => ['created' => 1], 'items' => ['created' => 3], 'fields' => ['created' => 1], 'usages' => ['created' => 1]], $result);
        $this->assertTrue(Record::getFieldDefinitions()['size']->usage()->isRequired);
        $entry = LogHelper::query(Field::class)->orderByDesc('log_id')->first();
        $this->assertSame('Moved from staging', LogHelper::decode($entry->data)['comment'] ?? null);

        $again = DefinitionsHelper::import($data);

        $this->assertSame(['lovs' => ['unchanged' => 1], 'items' => ['unchanged' => 3], 'fields' => ['unchanged' => 1], 'usages' => ['unchanged' => 1]], $again);
    }

    public function test_import_updates_and_skips_system_records(): void
    {
        $this->createDefinitions();
        $data = DefinitionsHelper::export();

        $data['fields'][0]['label'] = 'Size of the record';
        $data['lovs'][] = ['code' => RecordStatus::CODE, 'label' => 'Changed'];

        $result = DefinitionsHelper::import($data);

        $this->assertSame(['unchanged' => 1, 'skipped' => 1], $result['lovs']);
        $this->assertSame(['updated' => 1], $result['fields']);
        $this->assertSame('Size of the record', Field::query()->where('code', 'size')->first()->label());
        $this->assertNotSame('Changed', Lov::query()->where('code', RecordStatus::CODE)->first()->label());
    }

    public function test_commands_round_trip_and_dry_run(): void
    {
        $this->createDefinitions();
        $file = tempnam(sys_get_temp_dir(), 'mc');

        $this->assertSame(0, Artisan::call('mutable-content:export-definitions', ['file' => $file]));
        $this->deleteDefinitions();

        $this->assertSame(0, Artisan::call('mutable-content:import-definitions', ['file' => $file, '--dry-run' => true]));
        $this->assertStringContainsString('fields: 1 created', Artisan::output());
        $this->assertNull(Field::query()->where('code', 'size')->first());

        $this->assertSame(0, Artisan::call('mutable-content:import-definitions', ['file' => $file]));
        $this->assertNotNull(Field::query()->where('code', 'size')->first());

        unlink($file);
    }
}
