<?php

namespace Amarenkov\MutableContent\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContent\Tests\Fixtures\Lovs\RecordStatus;
use Amarenkov\MutableContent\Tests\Fixtures\Lovs\TicketKind;
use Amarenkov\MutableContent\Tests\Fixtures\Models\OwnedTicket;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Ticket;

class FixturesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(MutableClassRegistry::class)
            ->add(Owner::class)
            ->add(Record::class)
            ->add(Ticket::class)
            ->add(OwnedTicket::class);

        $this->app->make(LovRegistry::class)
            ->addClass(RecordStatus::class)
            ->addClass(TicketKind::class);
    }
}
