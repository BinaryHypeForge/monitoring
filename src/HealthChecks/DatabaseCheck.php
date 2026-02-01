<?php

namespace BinaryHype\Monitoring\HealthChecks;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseCheck implements HealthCheckInterface
{
    protected ?string $errorMessage = null;

    public function name(): string
    {
        return 'database';
    }

    public function check(): bool
    {
        try {
            DB::connection()->getPdo();

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
