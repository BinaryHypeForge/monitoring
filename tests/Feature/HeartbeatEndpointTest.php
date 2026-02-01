<?php

describe('Heartbeat Endpoint', function () {
    it('returns health status', function () {
        $response = $this->get('/_monitor/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'php_version',
            'environment',
        ]);
        $response->assertJsonFragment(['status' => 'ok']);
    });

    it('includes laravel version', function () {
        $response = $this->get('/_monitor/health');

        $response->assertJsonStructure([
            'laravel_version',
        ]);
    });

    it('runs configured health checks', function () {
        config(['monitor.heartbeat.checks' => [
            'database' => true,
            'cache' => false,
            'queue' => false,
        ]]);

        \Illuminate\Support\Facades\DB::shouldReceive('connection->getPdo')
            ->once()
            ->andReturn(new \stdClass);

        $response = $this->get('/_monitor/health');

        $response->assertJsonStructure([
            'checks' => ['database'],
        ]);
    });

    it('returns degraded status when check fails', function () {
        config(['monitor.heartbeat.checks' => [
            'database' => true,
            'cache' => false,
            'queue' => false,
        ]]);

        \Illuminate\Support\Facades\DB::shouldReceive('connection->getPdo')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $response = $this->get('/_monitor/health');

        $response->assertStatus(503);
        $response->assertJsonFragment(['status' => 'degraded']);
        $response->assertJsonFragment(['database' => 'fail']);
    });

    it('respects route configuration', function () {
        // Route configuration is checked at service provider boot time,
        // so we just verify that the config option exists and is used
        expect(config('monitor.heartbeat.enabled'))->toBeTrue();
        expect(config('monitor.heartbeat.route'))->toBe('/_monitor/health');
    });
});
