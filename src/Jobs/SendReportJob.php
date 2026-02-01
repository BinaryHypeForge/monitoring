<?php

namespace BinaryHype\Monitoring\Jobs;

use BinaryHype\Monitoring\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $backoff;

    public function __construct(
        public string $type,
        public array $payload
    ) {
        $this->tries = config('monitor.retry.times', 3);
        $this->backoff = (int) ceil(config('monitor.retry.sleep', 100) / 1000);
    }

    public function handle(Monitor $monitor): void
    {
        match ($this->type) {
            'error' => $monitor->getClient()->sendError($this->payload),
            'log' => $monitor->getClient()->sendLog($this->payload),
            default => null,
        };
    }

    public function failed(?\Throwable $exception): void
    {
        error_log('Monitor: Failed to send '.$this->type.' report: '.($exception?->getMessage() ?? 'Unknown error'));
    }
}
