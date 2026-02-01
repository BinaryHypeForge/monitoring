<?php

namespace BinaryHype\Monitoring\HealthChecks;

interface HealthCheckInterface
{
    public function name(): string;

    public function check(): bool;

    public function message(): ?string;
}
