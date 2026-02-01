<?php

namespace BinaryHype\Monitoring\HealthChecks;

use Illuminate\Support\Facades\Cache;
use Throwable;

class CacheCheck implements HealthCheckInterface
{
    protected ?string $errorMessage = null;

    public function name(): string
    {
        return 'cache';
    }

    public function check(): bool
    {
        try {
            $key = '_monitor_health_check_'.uniqid();
            $value = 'test_'.time();

            Cache::put($key, $value, 10);

            $retrieved = Cache::get($key);

            Cache::forget($key);

            if ($retrieved !== $value) {
                $this->errorMessage = 'Cache read/write verification failed';

                return false;
            }

            return true;
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return false;
        }
    }

    public function message(): ?string
    {
        return $this->errorMessage;
    }
}
