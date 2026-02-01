<?php

namespace BinaryHype\Monitoring\Commands;

use BinaryHype\Monitoring\Monitor;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestCommand extends Command
{
    protected $signature = 'monitor:test';

    protected $description = 'Test the monitoring integration';

    public function __construct(protected Monitor $monitor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Testing Monitoring Integration...');
        $this->newLine();

        $hasError = false;

        // Check configuration
        if (! $this->checkConfiguration()) {
            $hasError = true;
        }

        // Test API endpoint
        if (! $this->testApiEndpoint()) {
            $hasError = true;
        }

        // Send test error
        if (! $this->sendTestError()) {
            $hasError = true;
        }

        // Test heartbeat endpoint
        if (! $this->testHeartbeatEndpoint()) {
            $hasError = true;
        }

        $this->newLine();

        if ($hasError) {
            $this->error('Some tests failed. Please check the errors above.');

            return Command::FAILURE;
        }

        $this->info('All tests passed! Your integration is working correctly.');

        return Command::SUCCESS;
    }

    protected function checkConfiguration(): bool
    {
        $apiKey = config('monitor.api_key');
        $endpoint = config('monitor.endpoint');

        if (empty($apiKey)) {
            $this->displayError('Configuration invalid', 'MONITOR_API_KEY is not set');

            return false;
        }

        if (empty($endpoint)) {
            $this->displayError('Configuration invalid', 'MONITOR_ENDPOINT is not set');

            return false;
        }

        $this->displaySuccess('Configuration valid');

        return true;
    }

    protected function testApiEndpoint(): bool
    {
        $endpoint = config('monitor.endpoint');

        $result = $this->monitor->getClient()->testConnection();

        if (! $result['success']) {
            $this->displayError(
                'API endpoint unreachable',
                "Could not connect to {$endpoint}",
                'Check your MONITOR_ENDPOINT and network connectivity'
            );

            return false;
        }

        $this->displaySuccess("API endpoint reachable ({$endpoint})");

        return true;
    }

    protected function sendTestError(): bool
    {
        try {
            $testException = new Exception('This is a test error from monitor:test command');

            $errorId = $this->monitor->captureException($testException, [
                'test' => true,
                'command' => 'monitor:test',
            ]);

            if ($errorId) {
                $this->displaySuccess("Test error sent successfully (ID: {$errorId})");

                return true;
            }

            if (! $this->monitor->isEnabled()) {
                $this->displayWarning(
                    'Monitoring is disabled',
                    'Check MONITOR_ENABLED and ignored_environments settings'
                );

                return true;
            }

            $this->displayError('Failed to send test error', 'No response received from server');

            return false;
        } catch (Exception $e) {
            $this->displayError('Failed to send test error', $e->getMessage());

            return false;
        }
    }

    protected function testHeartbeatEndpoint(): bool
    {
        if (! config('monitor.heartbeat.enabled', true)) {
            $this->displayWarning('Heartbeat endpoint disabled', 'Skipping heartbeat test');

            return true;
        }

        $route = config('monitor.heartbeat.route', '/_monitor/health');

        try {
            $baseUrl = config('app.url', 'http://localhost');
            $url = rtrim($baseUrl, '/').$route;

            $response = Http::timeout(5)->get($url);

            if ($response->successful()) {
                $this->displaySuccess("Heartbeat endpoint accessible ({$route})");

                return true;
            }

            $this->displayWarning(
                "Heartbeat endpoint returned {$response->status()}",
                "The endpoint at {$route} is accessible but returned a non-200 status"
            );

            return true;
        } catch (Exception $e) {
            $this->displayWarning(
                "Could not reach heartbeat endpoint",
                "Unable to access {$route} locally. This may be normal if the app is not running."
            );

            return true;
        }
    }

    protected function displaySuccess(string $message): void
    {
        $this->line("<fg=green>✓</> {$message}");
    }

    protected function displayError(string $message, ?string $detail = null, ?string $suggestion = null): void
    {
        $this->line("<fg=red>✗</> {$message}");

        if ($detail) {
            $this->line("  <fg=gray>→ {$detail}</>");
        }

        if ($suggestion) {
            $this->line("  <fg=gray>→ {$suggestion}</>");
        }
    }

    protected function displayWarning(string $message, ?string $detail = null): void
    {
        $this->line("<fg=yellow>!</> {$message}");

        if ($detail) {
            $this->line("  <fg=gray>→ {$detail}</>");
        }
    }
}
