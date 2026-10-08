<?php

namespace Amarenkov\MutableContent\Tests\Feature\Helpers;

use Amarenkov\MutableContent\Helpers\ObjectHelper;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;

class ObjectHelperTest extends FeatureTestCase
{
    public function test_options_search_is_case_insensitive(): void
    {
        foreach ([['OWN-1', 'Acme Works'], ['OWN-2', 'Globex'], ['own-3', 'Initech']] as [$code, $label]) {
            $owner = new Owner();
            $owner->fill(['code' => $code, 'label' => $label]);
            $owner->save();
        }

        $this->assertSame(['Acme Works'], array_values(ObjectHelper::getOptions(Owner::class, 'acme')));
        $this->assertSame(['Acme Works', 'Globex', 'Initech'], array_values(ObjectHelper::getOptions(Owner::class, 'Own-')));
        $this->assertSame(['own-3' => 'Initech'], ObjectHelper::getOptions(Owner::class, 'INITECH', byCode: true));
        $this->assertSame([], ObjectHelper::getOptions(Owner::class, '50%'));
    }
}
