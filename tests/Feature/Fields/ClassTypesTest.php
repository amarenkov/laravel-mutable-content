<?php

namespace Amarenkov\MutableContent\Tests\Feature\Fields;

use InvalidArgumentException;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Models\Field\Field;
use Amarenkov\MutableContent\Models\Field\Usage;
use Amarenkov\MutableContent\Models\Lov\Item;
use Amarenkov\MutableContent\Models\Lov\Lov;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Lovs\TicketKind;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;
use Amarenkov\MutableContent\Tests\Fixtures\Models\OwnedTicket;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Ticket;

class ClassTypesTest extends FeatureTestCase
{
    protected function makeField(string $code, string $type = Type::TYPE_STRING): Field
    {
        $field = new Field();
        $field->fill(['code' => $code, 'field_type' => $type, 'label' => ucfirst($code)]);
        $field->save();

        return $field;
    }

    protected function bind(Field $field, string $class, ?string $typeCode = null): Usage
    {
        $usage = new Usage();
        $usage->fill([Usage::FIELD_FIELD_ID => $field->id, Usage::CODE_MUTABLE_CLASS => $class, Usage::CODE_TYPE_CODE => $typeCode]);
        $usage->save();

        return $usage;
    }

    protected function makeTicket(string $kind, array $fields = []): Ticket
    {
        $ticket = new Ticket();
        $ticket->fill(['label' => 'Ticket', Ticket::FIELD_KIND => $kind] + $fields);
        $ticket->save();

        return $ticket;
    }

    public function test_types_of_a_class_come_from_its_type_field(): void
    {
        $this->assertSame([TicketKind::BUG => 'Bug', TicketKind::FEATURE => 'Feature'], Ticket::getTypeOptions());
        $this->assertSame([], Owner::getTypeOptions());
        $this->assertNull(Owner::typeField());
    }

    public function test_usage_bound_to_a_type_gets_a_type_scope(): void
    {
        $usage = $this->bind($this->makeField('severity'), Ticket::class, TicketKind::BUG);

        $this->assertSame(Usage::makeTypeScope(Ticket::class, TicketKind::BUG), $usage->scope);
        $this->assertSame(Ticket::class, Usage::getScopeMutableClass($usage->scope));
        $this->assertSame(TicketKind::BUG, Usage::getScopeTypeCode($usage->scope));
        $this->assertNull(Usage::getScopeTypeCode(Ticket::getClassScope()));
    }

    public function test_objects_of_different_types_have_different_fields(): void
    {
        $this->bind($this->makeField('severity'), Ticket::class, TicketKind::BUG);
        $this->bind($this->makeField('benefit'), Ticket::class, TicketKind::FEATURE);
        $this->bind($this->makeField('reporter'), Ticket::class);

        $bug = $this->makeTicket(TicketKind::BUG, ['severity' => 'high', 'reporter' => 'Ann']);
        $feature = $this->makeTicket(TicketKind::FEATURE, ['benefit' => 'speed', 'reporter' => 'Bob']);

        $this->assertSame(TicketKind::BUG, $bug->typeCode());
        $this->assertArrayHasKey('severity', $bug->fieldDefinitions());
        $this->assertArrayNotHasKey('benefit', $bug->fieldDefinitions());
        $this->assertArrayHasKey('benefit', $feature->fieldDefinitions());
        $this->assertArrayNotHasKey('severity', $feature->fieldDefinitions());

        $this->assertSame('high', $bug->fresh()->severity);
        $this->assertSame('Bob', $feature->fresh()->reporter);

        $this->assertArrayNotHasKey('severity', Ticket::getFieldDefinitions());
        $this->assertArrayHasKey('severity', Ticket::getFieldDefinitions(Ticket::getFieldScopesForType(TicketKind::BUG)));
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->bind($this->makeField('severity'), Ticket::class, 'no_such_kind');
    }

    public function test_type_given_by_an_object_reference(): void
    {
        $owner = new Owner();
        $owner->fill(['code' => 'acme', 'label' => 'Acme']);
        $owner->save();

        $this->assertSame(['acme' => 'Acme'], OwnedTicket::getTypeOptions());

        $this->bind($this->makeField('contract'), OwnedTicket::class, 'acme');

        $ticket = new OwnedTicket();
        $ticket->fill(['label' => 'Ticket', OwnedTicket::FIELD_OWNER_ID => $owner->id, 'contract' => 'C-1']);
        $ticket->save();

        $this->assertSame('acme', $ticket->typeCode());
        $this->assertSame('C-1', $ticket->fresh()->contract);
    }

    public function test_lov_items_take_fields_by_their_lov(): void
    {
        $lov = new Lov();
        $lov->fill(['code' => 'vehicle', 'label' => 'Vehicle']);
        $lov->save();

        $other = new Lov();
        $other->fill(['code' => 'colour', 'label' => 'Colour']);
        $other->save();

        $usage = $this->bind($this->makeField('capacity', Type::TYPE_INT), Item::class, 'vehicle');

        $this->assertSame(Item::getTypeScope('vehicle'), $usage->scope);
        $this->assertArrayHasKey('vehicle', Item::getTypeOptions());
        $this->assertTrue($lov->fresh()->isUsedInFields());
        $this->assertFalse($other->fresh()->isUsedInFields());

        $truck = new Item();
        $truck->fill([Item::FIELD_LOV_ID => $lov->id, 'code' => 'truck', 'label' => 'Truck', 'capacity' => 20]);
        $truck->save();

        $red = new Item();
        $red->fill([Item::FIELD_LOV_ID => $other->id, 'code' => 'red', 'label' => 'Red']);
        $red->save();

        $this->assertSame(20, $truck->fresh()->capacity);
        $this->assertArrayHasKey('capacity', $truck->fieldDefinitions());
        $this->assertArrayNotHasKey('capacity', $red->fieldDefinitions());
    }

    public function test_usage_is_filled_back_from_a_type_scope(): void
    {
        $usage = new Usage();
        $usage->fillFromScope(Usage::makeTypeScope(Ticket::class, TicketKind::FEATURE));

        $this->assertSame(Ticket::class, $usage->{Usage::CODE_MUTABLE_CLASS});
        $this->assertSame(TicketKind::FEATURE, $usage->{Usage::CODE_TYPE_CODE});
    }

    public function test_code_field_bound_to_a_type(): void
    {
        $this->assertArrayNotHasKey(Ticket::FIELD_STEPS, Ticket::getFieldDefinitions());
        $this->assertArrayHasKey(Ticket::FIELD_STEPS, Ticket::getFieldDefinitions(Ticket::getFieldScopesForType(TicketKind::BUG)));
        $this->assertArrayNotHasKey(Ticket::FIELD_STEPS, Ticket::getFieldDefinitions(Ticket::getFieldScopesForType(TicketKind::FEATURE)));

        $field = Field::where('fields->code', Ticket::FIELD_STEPS)->first();

        $this->assertTrue($field->isSystem());
        $this->assertSame([Ticket::getTypeScope(TicketKind::BUG)], $field->usages()->pluck('scope')->all());

        $bug = $this->makeTicket(TicketKind::BUG, [Ticket::FIELD_STEPS => 'Open and click']);

        $this->assertSame('Open and click', $bug->fresh()->{Ticket::FIELD_STEPS});
    }
}
