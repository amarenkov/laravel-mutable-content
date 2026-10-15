<?php

namespace Amarenkov\MutableContent\Tests\Feature\Models;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Helpers\SchemaHelper;
use Amarenkov\MutableContent\Models\Field\Field;
use Amarenkov\MutableContent\Models\Field\Usage;
use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class MetadataCacheTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SchemaHelper::flush();
        ModelWithFields::flushExtractedFields();
    }

    protected function bindNewField(string $code, string $class): void
    {
        $field = new Field();
        $field->fill(['code' => $code, 'field_type' => Type::TYPE_TEXT, 'label' => ucfirst($code)]);
        $field->save();

        $usage = new Usage();
        $usage->fill([Usage::FIELD_FIELD_ID => $field->id, Usage::CODE_MUTABLE_CLASS => $class]);
        $usage->save();
    }

    /**
     * @return array<string>
     */
    protected function queriesTo(string $table): array
    {
        return array_values(array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $query) => str_contains($query, $table)));
    }

    public function test_usages_of_all_classes_are_loaded_by_one_query(): void
    {
        $this->bindNewField('comment', Record::class);
        $this->bindNewField('note', Owner::class);

        DB::enableQueryLog();

        $record = Record::getFieldDefinitions();
        $owner = Owner::getFieldDefinitions();

        $this->assertCount(1, $this->queriesTo('fields_usage'));
        $this->assertArrayHasKey('comment', $record);
        $this->assertArrayNotHasKey('note', $record);
        $this->assertArrayHasKey('note', $owner);
        $this->assertArrayNotHasKey('comment', $owner);
    }

    public function test_definitions_do_not_share_fields_between_scope_sets(): void
    {
        $this->bindNewField('comment', Record::class);

        $scopes = Record::getFieldScopes();

        Record::getFieldDefinitions($scopes)['comment']->label('Changed');

        $this->assertSame('Comment', Record::getFieldDefinitions([...$scopes, ...$scopes])['comment']->label);
    }

    public function test_generated_columns_are_kept_in_the_cache(): void
    {
        $this->assertSame(['code' => 20], Owner::getFieldMaxLengths());

        $key = DB::connection()->getName().':'.$this->tableOf(Owner::class);

        $this->assertArrayHasKey($key, Cache::get(SchemaHelper::CACHE_KEY));
        $this->assertSame(Owner::getExtractedFields(), Cache::get(SchemaHelper::CACHE_KEY)[$key]);
    }

    public function test_generated_columns_are_read_from_the_cache(): void
    {
        $key = DB::connection()->getName().':'.$this->tableOf(Owner::class);

        Cache::forever(SchemaHelper::CACHE_KEY, [$key => ['code' => 'varchar(7)']]);

        $this->assertSame(['code' => 7], Owner::getFieldMaxLengths());
    }

    public function test_migrations_drop_the_cached_generated_columns(): void
    {
        $key = DB::connection()->getName().':'.$this->tableOf(Owner::class);

        Cache::forever(SchemaHelper::CACHE_KEY, [$key => ['code' => 'varchar(7)']]);

        $this->assertSame(['code' => 7], Owner::getFieldMaxLengths());

        event(new MigrationsEnded('up'));

        $this->assertNull(Cache::get(SchemaHelper::CACHE_KEY));
        $this->assertSame(['code' => 20], Owner::getFieldMaxLengths());
    }

    public function test_schema_cache_can_be_turned_off(): void
    {
        config(['mutable-content.schema_cache' => false]);

        $this->assertSame(['code' => 20], Owner::getFieldMaxLengths());
        $this->assertNull(Cache::get(SchemaHelper::CACHE_KEY));
    }
}
