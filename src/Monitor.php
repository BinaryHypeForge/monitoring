<?php

namespace BinaryHype\Monitoring;

use BinaryHype\Monitoring\Http\Client;
use Throwable;

class Monitor
{
    protected Client $client;

    protected array $config;

    protected array $user = [];

    protected array $context = [];

    protected array $tags = [];

    protected array $pendingReports = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->client = new Client($this->config);
    }

    protected function getDefaultConfig(): array
    {
        return [
            'api_key' => null,
            'endpoint' => 'https://monitoring.binary-hype.com/api/v1',
            'enabled' => true,
            'environment' => 'production',
            'capture_user' => true,
            'capture_request' => true,
            'capture_session' => false,
            'ignored_exceptions' => [],
            'ignored_environments' => ['local', 'testing'],
            'sample_rate' => 1.0,
            'filtered_fields' => [
                'password',
                'password_confirmation',
                'secret',
                'token',
                'api_key',
                'credit_card',
                'card_number',
                'cvv',
            ],
            'max_payload_size' => 65536,
            'timeout' => 5,
            'retry' => [
                'times' => 3,
                'sleep' => 100,
            ],
        ];
    }

    public function isEnabled(): bool
    {
        if (! $this->config['enabled']) {
            return false;
        }

        if (empty($this->config['api_key'])) {
            return false;
        }

        $environment = $this->config['environment'];
        $ignoredEnvironments = $this->config['ignored_environments'] ?? [];

        if (in_array($environment, $ignoredEnvironments, true)) {
            return false;
        }

        return true;
    }

    public function shouldSample(): bool
    {
        $sampleRate = $this->config['sample_rate'] ?? 1.0;

        if ($sampleRate >= 1.0) {
            return true;
        }

        if ($sampleRate <= 0.0) {
            return false;
        }

        return mt_rand() / mt_getrandmax() <= $sampleRate;
    }

    public function captureException(Throwable $exception, array $context = []): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($this->isIgnoredException($exception)) {
            return null;
        }

        if (! $this->shouldSample()) {
            return null;
        }

        $payload = $this->buildExceptionPayload($exception, $context);

        return $this->sendError($payload);
    }

    public function captureMessage(string $message, string $level = 'info', array $context = []): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if (! $this->shouldSample()) {
            return null;
        }

        $payload = $this->buildMessagePayload($message, $level, $context);

        return $this->sendError($payload);
    }

    public function setUser(array $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): array
    {
        return $this->user;
    }

    public function setContext(string $key, array $data): self
    {
        $this->context[$key] = $data;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setTags(array $tags): self
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function flush(): void
    {
        foreach ($this->pendingReports as $report) {
            $this->client->sendError($report);
        }

        $this->pendingReports = [];
    }

    public function registerExceptionHandler(): self
    {
        set_exception_handler(function (Throwable $exception) {
            $this->captureException($exception);

            throw $exception;
        });

        return $this;
    }

    public function registerErrorHandler(): self
    {
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
            if (! (error_reporting() & $errno)) {
                return false;
            }

            $exception = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            $this->captureException($exception);

            return false;
        });

        return $this;
    }

    public function registerShutdownHandler(): self
    {
        register_shutdown_function(function () {
            $error = error_get_last();

            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $exception = new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                );

                $this->captureException($exception);
            }

            $this->flush();
        });

        return $this;
    }

    protected function isIgnoredException(Throwable $exception): bool
    {
        $ignoredExceptions = $this->config['ignored_exceptions'] ?? [];

        foreach ($ignoredExceptions as $ignoredException) {
            if ($exception instanceof $ignoredException) {
                return true;
            }
        }

        return false;
    }

    protected function buildExceptionPayload(Throwable $exception, array $context = []): array
    {
        $payload = [
            'message' => $exception->getMessage(),
            'level' => 'error',
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString(),
            'exception_class' => get_class($exception),
            'environment' => $this->config['environment'],
            'timestamp' => date('c'),
        ];

        if (! empty($this->user)) {
            $payload['user'] = $this->user;
        }

        if (! empty($context) || ! empty($this->context)) {
            $payload['context'] = array_merge($this->context, $context);
        }

        if (! empty($this->tags)) {
            $payload['tags'] = $this->tags;
        }

        return $this->filterPayload($payload);
    }

    protected function buildMessagePayload(string $message, string $level, array $context = []): array
    {
        $payload = [
            'message' => $message,
            'level' => $level,
            'file' => null,
            'line' => null,
            'stack_trace' => null,
            'environment' => $this->config['environment'],
            'timestamp' => date('c'),
        ];

        if (! empty($this->user)) {
            $payload['user'] = $this->user;
        }

        if (! empty($context) || ! empty($this->context)) {
            $payload['context'] = array_merge($this->context, $context);
        }

        if (! empty($this->tags)) {
            $payload['tags'] = $this->tags;
        }

        return $this->filterPayload($payload);
    }

    protected function filterPayload(array $payload): array
    {
        $filteredFields = $this->config['filtered_fields'] ?? [];

        array_walk_recursive($payload, function (&$value, $key) use ($filteredFields) {
            if (in_array(strtolower($key), array_map('strtolower', $filteredFields), true)) {
                $value = '[FILTERED]';
            }
        });

        $encoded = json_encode($payload);
        $maxSize = $this->config['max_payload_size'] ?? 65536;

        if (strlen($encoded) > $maxSize) {
            if (isset($payload['stack_trace'])) {
                $payload['stack_trace'] = substr($payload['stack_trace'], 0, 5000).'... [TRUNCATED]';
            }

            if (isset($payload['context'])) {
                $payload['context'] = ['_truncated' => true, '_message' => 'Context truncated due to size limits'];
            }
        }

        return $payload;
    }

    protected function sendError(array $payload): ?string
    {
        return $this->client->sendError($payload);
    }

    public function sendLog(array $payload): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return $this->client->sendLog($payload);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
