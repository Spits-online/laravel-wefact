<?php

declare(strict_types=1);

namespace SpitsOnline\WeFact\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SpitsOnline\WeFact\WeFactServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WeFactServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('wefact.driver', 'hostfact');
        $app['config']->set('wefact.url', HOSTFACT);
        $app['config']->set('wefact.key', 'secret-key');
        $app['config']->set('app.timezone', 'Europe/Amsterdam');
    }
}
