<?php

namespace BinaryHype\Monitoring\HealthChecks;

use Illuminate\Support\Facades\Queue;
use Throwable;

class QueueCheck implements HealthCheckInterface
{
    protected ?string $errorMessage = null;

    public function name(): string
    {
        return 'queue';
    }

    public function check(): bool
    {
        try {
            $connection = Queue::connection();

            $connection->size();

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
