<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests;

use Illuminate\Foundation\Application;
use Marmol89\Cauce\CauceServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            CauceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('queue.default', 'sync');
        $app['config']->set('queue.connections.sync', [
            'driver' => 'sync',
        ]);

        $app['config']->set('cauce.enabled', true);
        $app['config']->set('cauce.path', 'cauce');
        $app['config']->set('cauce.middleware', ['web']);
        $app['config']->set('cauce.allow_production', true);
        $app['config']->set('cauce.storage.database.connection', null);
        $app['config']->set('cauce.monitoring.sample_rate', 1.0);
    }
}
