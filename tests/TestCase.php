<?php

namespace BinaryHype\Monitoring\Tests;

use BinaryHype\Monitoring\MonitorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            MonitorServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Monitor' => \BinaryHype\Monitoring\Facades\Monitor::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('monitor.api_key', 'test-api-key');
        $app['config']->set('monitor.endpoint', 'https://test.monitoring.example.com/api/v1');
        $app['config']->set('monitor.enabled', true);
        $app['config']->set('monitor.environment', 'testing');
        $app['config']->set('monitor.ignored_environments', []);
        $app['config']->set('monitor.queue.enabled', false);
    }
}
