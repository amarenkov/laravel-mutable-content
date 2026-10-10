<?php

namespace Amarenkov\MutableContent\Tests\Fixtures\Lovs;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(TicketKind::CODE)]
#[ClassLabel('Ticket kind')]
#[LovItem(TicketKind::BUG, 'Bug')]
#[LovItem(TicketKind::FEATURE, 'Feature')]
class TicketKind
{
    // const
    public const CODE = 'ticket_kind';

    public const BUG = 'bug';
    public const FEATURE = 'feature';
}
