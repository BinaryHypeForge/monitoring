<?php

namespace BinaryHype\Monitoring\Http\Controllers;

use BinaryHype\Monitoring\HealthChecks\CacheCheck;
use BinaryHype\Monitoring\HealthChecks\DatabaseCheck;
use BinaryHype\Monitoring\HealthChecks\HealthCheckInterface;
use BinaryHype\Monitoring\HealthChecks\QueueCheck;
use Illuminate\Http\JsonResponse;

class HeartbeatController
{
    protected array $availableChecks = [
        'database' => DatabaseCheck::class,
        'cache' => CacheCheck::class,
        'queue' => QueueCheck::class,
    ];

    public function __invoke(): JsonResponse
    {
        $response = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'environment' => config('monitor.environment', config('app.env', 'production')),
        ];

        if (class_exists(\Illuminate\Foundation\Application::class)) {
            $response['laravel_version'] = app()->version();
        }

        $appVersion = config('app.version');
        if ($appVersion) {
            $response['app_version'] = $appVersion;
        }

        $checks = $this->runHealthChecks();
        if (! empty($checks)) {
            $response['checks'] = $checks;

            $hasFailure = collect($checks)->contains(fn ($status) => $status !== 'ok');
            if ($hasFailure) {
                $response['status'] = 'degraded';
            }
        }

        $statusCode = $response['status'] === 'ok' ? 200 : 503;

        return response()->json($response, $statusCode);
    }

    protected function runHealthChecks(): array
    {
        $enabledChecks = config('monitor.heartbeat.checks', []);
        $results = [];

        foreach ($enabledChecks as $checkName => $enabled) {
            if (! $enabled) {
                continue;
            }

            if (! isset($this->availableChecks[$checkName])) {
                continue;
            }

            $checkClass = $this->availableChecks[$checkName];

            /** @var HealthCheckInterface $check */
            $check = new $checkClass;

            $passed = $check->check();

            $results[$checkName] = $passed ? 'ok' : 'fail';
        }

        return $results;
    }
}
