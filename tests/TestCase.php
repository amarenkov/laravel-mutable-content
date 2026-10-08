<?php

namespace Amarenkov\MutableContent\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

use Amarenkov\MutableContent\MutableContentServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            MutableContentServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'en');
    }
}
