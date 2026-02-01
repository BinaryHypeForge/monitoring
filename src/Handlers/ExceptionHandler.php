<?php

namespace BinaryHype\Monitoring\Handlers;

use BinaryHype\Monitoring\Jobs\SendReportJob;
use BinaryHype\Monitoring\Monitor;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ExceptionHandler implements ExceptionHandlerContract
{
    protected ExceptionHandlerContract $originalHandler;

    protected Monitor $monitor;

    public function __construct(ExceptionHandlerContract $originalHandler, Monitor $monitor)
    {
        $this->originalHandler = $originalHandler;
        $this->monitor = $monitor;
    }

    public function report(Throwable $e): void
    {
        $this->captureException($e);
        $this->originalHandler->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->originalHandler->shouldReport($e);
    }

    public function render($request, Throwable $e): mixed
    {
        return $this->originalHandler->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->originalHandler->renderForConsole($output, $e);
    }

    protected function captureException(Throwable $exception): void
    {
        if (! $this->monitor->isEnabled()) {
            return;
        }

        if ($this->isIgnoredException($exception)) {
            return;
        }

        if (! $this->monitor->shouldSample()) {
            return;
        }

        $payload = $this->buildPayload($exception);

        if ($this->shouldQueue()) {
            $this->dispatchJob($payload);
        } else {
            $this->monitor->getClient()->sendError($payload);
        }
    }

    protected function isIgnoredException(Throwable $exception): bool
    {
        $ignoredExceptions = config('monitor.ignored_exceptions', []);

        foreach ($ignoredExceptions as $ignoredException) {
            if ($exception instanceof $ignoredException) {
                return true;
            }
        }

        return false;
    }

    protected function buildPayload(Throwable $exception): array
    {
        $payload = [
            'message' => $exception->getMessage(),
            'level' => 'error',
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString(),
            'exception_class' => get_class($exception),
            'environment' => config('monitor.environment', config('app.env', 'production')),
            'timestamp' => now()->toIso8601String(),
        ];

        if (config('monitor.capture_user', true)) {
            $payload['user'] = $this->captureUser();
        }

        if (config('monitor.capture_request', true)) {
            $payload['request'] = $this->captureRequest();
        }

        if (config('monitor.capture_session', false)) {
            $payload['session'] = $this->captureSession();
        }

        $monitorContext = $this->monitor->getContext();
        if (! empty($monitorContext)) {
            $payload['context'] = $monitorContext;
        }

        $monitorTags = $this->monitor->getTags();
        if (! empty($monitorTags)) {
            $payload['tags'] = $monitorTags;
        }

        $monitorUser = $this->monitor->getUser();
        if (! empty($monitorUser)) {
            $payload['user'] = array_merge($payload['user'] ?? [], $monitorUser);
        }

        return $this->filterPayload($payload);
    }

    protected function captureUser(): array
    {
        try {
            $user = Auth::user();

            if ($user === null) {
                return [];
            }

            return [
                'id' => $user->getAuthIdentifier(),
                'email' => $user->email ?? null,
                'name' => $user->name ?? null,
            ];
        } catch (Throwable) {
            return [];
        }
    }

    protected function captureRequest(): array
    {
        try {
            $request = request();

            if (! $request instanceof Request) {
                return [];
            }

            return [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'headers' => $this->filterHeaders($request->headers->all()),
                'input' => $this->filterInput($request->except(config('monitor.filtered_fields', []))),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    protected function captureSession(): array
    {
        try {
            if (! session()->isStarted()) {
                return [];
            }

            return $this->filterInput(session()->all());
        } catch (Throwable) {
            return [];
        }
    }

    protected function filterHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];

        return array_map(function ($value, $key) use ($sensitiveHeaders) {
            if (in_array(strtolower($key), $sensitiveHeaders, true)) {
                return ['[FILTERED]'];
            }

            return $value;
        }, $headers, array_keys($headers));
    }

    protected function filterInput(array $input): array
    {
        $filteredFields = config('monitor.filtered_fields', []);

        array_walk_recursive($input, function (&$value, $key) use ($filteredFields) {
            if (in_array(strtolower($key), array_map('strtolower', $filteredFields), true)) {
                $value = '[FILTERED]';
            }
        });

        return $input;
    }

    protected function filterPayload(array $payload): array
    {
        $encoded = json_encode($payload);
        $maxSize = config('monitor.max_payload_size', 65536);

        if (strlen($encoded) > $maxSize) {
            if (isset($payload['stack_trace'])) {
                $payload['stack_trace'] = substr($payload['stack_trace'], 0, 5000).'... [TRUNCATED]';
            }

            if (isset($payload['context'])) {
                $payload['context'] = ['_truncated' => true];
            }

            if (isset($payload['request']['input'])) {
                $payload['request']['input'] = ['_truncated' => true];
            }
        }

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

        SendReportJob::dispatch('error', $payload)
            ->onConnection($connection)
            ->onQueue($queue);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->originalHandler->{$method}(...$parameters);
    }
}
