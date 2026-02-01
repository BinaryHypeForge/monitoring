# Monitor Client

A PHP/Laravel package to send errors, logs, and heartbeats to the Monitoring Monolith.

## Installation

### Laravel

```bash
composer require binary-hype/monitoring
```

The package uses Laravel's auto-discovery, so the service provider is registered automatically.

Publish the configuration file:

```bash
php artisan vendor:publish --tag=monitor-config
```

### Vanilla PHP

```bash
composer require binary-hype/monitoring
```

Manual initialization required (see [Vanilla PHP Usage](#vanilla-php-usage)).

## Configuration

Add these to your `.env` file:

```env
MONITOR_API_KEY=your-project-api-key-here
MONITOR_ENDPOINT=https://monitoring.binary-hype.com/api/v1
MONITOR_ENABLED=true
MONITOR_ENVIRONMENT=production
```

### Configuration Options

| Option | Default | Description |
|--------|---------|-------------|
| `api_key` | `null` | Your project's API key from the monitoring dashboard |
| `endpoint` | `https://monitoring.binary-hype.com/api/v1` | The monitoring server URL |
| `enabled` | `true` | Toggle monitoring on/off |
| `environment` | `APP_ENV` | Environment name for error tagging |
| `capture_user` | `true` | Include authenticated user info |
| `capture_request` | `true` | Include request data |
| `capture_session` | `false` | Include session data |
| `sample_rate` | `1.0` | Percentage of errors to capture (0.0 to 1.0) |
| `queue.enabled` | `true` | Send reports via queue |
| `queue.connection` | `redis` | Queue connection to use |
| `queue.queue` | `monitor` | Queue name |

### Ignored Exceptions

By default, these exceptions are not reported:

- `Illuminate\Validation\ValidationException`
- `Symfony\Component\HttpKernel\Exception\NotFoundHttpException`
- `Illuminate\Auth\AuthenticationException`
- `Illuminate\Session\TokenMismatchException`

### Filtered Fields

Sensitive fields are automatically redacted from reports:

- `password`, `password_confirmation`
- `secret`, `token`, `api_key`
- `credit_card`, `card_number`, `cvv`

## Usage

### Automatic Exception Capture

Once installed and configured, exceptions are captured automatically. No code changes needed.

### Manual Error Reporting

```php
use BinaryHype\Monitoring\Facades\Monitor;

// Report an exception
try {
    // risky code
} catch (\Exception $e) {
    Monitor::captureException($e);
}

// Report with additional context
Monitor::captureException($e, [
    'order_id' => 123,
    'action' => 'checkout',
]);

// Report a custom message
Monitor::captureMessage('Something unexpected happened', 'warning', [
    'custom_context' => 'value',
]);
```

### Adding Context

```php
use BinaryHype\Monitoring\Facades\Monitor;

// Set user context (if not using Laravel's auth)
Monitor::setUser([
    'id' => 123,
    'email' => 'user@example.com',
    'name' => 'John Doe',
]);

// Add custom context to all future reports
Monitor::setContext('order', [
    'order_id' => 456,
    'total' => 99.99,
]);

// Add tags
Monitor::setTags([
    'feature' => 'checkout',
    'version' => '2.1.0',
]);
```

### Log Channel

Add the monitor channel to `config/logging.php`:

```php
'channels' => [
    // ... other channels

    'monitor' => [
        'driver' => 'monitor',
        'level' => env('MONITOR_LOG_LEVEL', 'error'),
    ],
],
```

Use it directly:

```php
Log::channel('monitor')->error('Payment failed', [
    'order_id' => 123,
    'reason' => 'Insufficient funds',
]);
```

Or add it to your stack:

```php
'stack' => [
    'driver' => 'stack',
    'channels' => ['daily', 'monitor'],
],
```

### Vanilla PHP Usage

```php
<?php

require 'vendor/autoload.php';

use BinaryHype\Monitoring\Monitor;

// Initialize the client
$monitor = new Monitor([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://monitoring.binary-hype.com/api/v1',
    'environment' => 'production',
]);

// Set as global exception handler
$monitor->registerExceptionHandler();
$monitor->registerErrorHandler();
$monitor->registerShutdownHandler();

// Or capture manually
try {
    // risky code
} catch (\Exception $e) {
    $monitor->captureException($e);
}
```

## Heartbeat Endpoint

The package automatically registers a health check endpoint that the monitoring server pings.

**Endpoint:** `GET /_monitor/health`

**Response:**

```json
{
  "status": "ok",
  "timestamp": "2026-02-01T12:00:00Z",
  "php_version": "8.3.0",
  "laravel_version": "12.0.0",
  "environment": "production",
  "checks": {
    "database": "ok",
    "cache": "ok"
  }
}
```

### Configure Health Checks

```php
// config/monitor.php

'heartbeat' => [
    'enabled' => true,
    'route' => '/_monitor/health',
    'middleware' => [], // Add middleware if needed
    'checks' => [
        'database' => true,
        'cache' => true,
        'queue' => false,
    ],
],
```

## Artisan Commands

### Test Integration

```bash
php artisan monitor:test
```

Validates configuration, sends a test error, and verifies the heartbeat endpoint.

**Example output:**

```
Testing Monitoring Integration...

✓ Configuration valid
✓ API endpoint reachable (https://monitoring.binary-hype.com/api/v1)
✓ Test error sent successfully (ID: err_abc123)
✓ Heartbeat endpoint accessible (/_monitor/health)

All tests passed! Your integration is working correctly.
```

## API Reference

### Monitor Facade

| Method | Description |
|--------|-------------|
| `captureException(Throwable $e, array $context = [])` | Capture and report an exception |
| `captureMessage(string $message, string $level, array $context = [])` | Report a custom message |
| `setUser(array $user)` | Set user context (`id`, `email`, `name`) |
| `setContext(string $key, array $data)` | Add custom context data |
| `setTags(array $tags)` | Add tags to all future reports |
| `flush()` | Force send all queued reports |
| `isEnabled()` | Check if monitoring is enabled |

## Troubleshooting

### Errors not appearing in dashboard

1. Check that `MONITOR_ENABLED=true`
2. Verify your `MONITOR_API_KEY` is correct
3. Ensure the environment is not in `ignored_environments`
4. Check that the exception type is not in `ignored_exceptions`
5. Run `php artisan monitor:test`

### Queue jobs failing

1. Ensure Redis is running
2. Check queue worker is processing the `monitor` queue
3. Review failed jobs: `php artisan queue:failed`

### Timeout errors

1. Increase the `timeout` config value
2. Enable queue-based reporting
3. Check network connectivity to monitoring server

## Testing

```bash
composer test
```

## Requirements

- PHP ^8.2
- Laravel ^10.0 | ^11.0 | ^12.0 (for Laravel integration)
- Guzzle ^7.0

## License

MIT License. See [LICENSE](LICENSE) for details.
