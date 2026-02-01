<?php

namespace BinaryHype\Monitoring\Handlers;

use BinaryHype\Monitoring\Jobs\SendReportJob;
use BinaryHype\Monitoring\Monitor;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class LogHandler extends AbstractProcessingHandler
{
    protected Monitor $monitor;

    public function __construct(Monitor $monitor, string $level = 'error')
    {
        $monologLevel = $this->parseLevel($level);
        parent::__construct($monologLevel, true);
        $this->monitor = $monitor;
    }

    protected function parseLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Error,
        };
    }

    protected function write(LogRecord $record): void
    {
        if (! $this->monitor->isEnabled()) {
            return;
        }

        $payload = $this->buildPayload($record);

        if ($this->shouldQueue()) {
            $this->dispatchJob($payload);
        } else {
            $this->monitor->sendLog($payload);
        }
    }

    protected function buildPayload(LogRecord $record): array
    {
        $payload = [
            'level' => strtolower($record->level->name),
            'message' => $record->message,
            'context' => $record->context,
            'environment' => config('monitor.environment', config('app.env', 'production')),
            'logged_at' => $record->datetime->format('c'),
        ];

        $monitorContext = $this->monitor->getContext();
        if (! empty($monitorContext)) {
            $payload['context'] = array_merge($payload['context'], $monitorContext);
        }

        $monitorTags = $this->monitor->getTags();
        if (! empty($monitorTags)) {
            $payload['tags'] = $monitorTags;
        }

        $monitorUser = $this->monitor->getUser();
        if (! empty($monitorUser)) {
            $payload['user'] = $monitorUser;
        }

        return $this->filterPayload($payload);
    }

    protected function filterPayload(array $payload): array
    {
        $filteredFields = config('monitor.filtered_fields', []);

        array_walk_recursive($payload, function (&$value, $key) use ($filteredFields) {
            if (in_array(strtolower($key), array_map('strtolower', $filteredFields), true)) {
                $value = '[FILTERED]';
            }
        });

        return $payload;
    }

    protected function shouldQueue(): bool
    {
        return config('monitor.queue.enabled', true);
    }

    protected function dispatchJob(array $payload): void
    {
        $connection = config('monitor.queue.connection', 'redis');
        $queue = config('monitor.queue.queue', 'monitor');

        SendReportJob::dispatch('log', $payload)
            ->onConnection($connection)
            ->onQueue($queue);
    }
}
