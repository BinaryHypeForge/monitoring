<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your project's API key from the monitoring dashboard. This is used to
    | authenticate requests to the monitoring server.
    |
    */
    'api_key' => env('MONITOR_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Endpoint
    |--------------------------------------------------------------------------
    |
    | The base URL of your monitoring monolith API. Do not include a trailing
    | slash.
    |
    */
    'endpoint' => env('MONITOR_ENDPOINT', 'https://monitoring.binary-hype.com/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Toggle monitoring on/off. Set to false in local development to prevent
    | sending test errors to your monitoring server.
    |
    */
    'enabled' => env('MONITOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The environment name to tag errors with. This helps you filter errors
    | by environment in the dashboard.
    |
    */
    'environment' => env('MONITOR_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Capture Options
    |--------------------------------------------------------------------------
    |
    | Control what additional context is captured with each error report.
    |
    */
    'capture_user' => true,
    'capture_request' => true,
    'capture_session' => false,

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | Exceptions listed here will not be reported to the monitoring server.
    | Useful for expected exceptions like 404s or validation errors.
    |
    */
    'ignored_exceptions' => [
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Session\TokenMismatchException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Environments
    |--------------------------------------------------------------------------
    |
    | Errors from these environments will not be reported.
    |
    */
    'ignored_environments' => [
        'local',
        'testing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Rate
    |--------------------------------------------------------------------------
    |
    | Percentage of errors to capture (0.0 to 1.0). Useful for high-traffic
    | applications to reduce noise. Set to 1.0 for 100% capture.
    |
    */
    'sample_rate' => 1.0,

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Send reports via queue for non-blocking operation. Recommended for
    | production to prevent monitoring from affecting response times.
    |
    */
    'queue' => [
        'enabled' => true,
        'connection' => env('MONITOR_QUEUE_CONNECTION', 'redis'),
        'queue' => env('MONITOR_QUEUE_NAME', 'monitor'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Endpoint
    |--------------------------------------------------------------------------
    |
    | The monolith pings this endpoint to check your application health.
    | This is pull-based (monolith → your app) not push-based.
    |
    */
    'heartbeat' => [
        'enabled' => true,
        'route' => '/_monitor/health',
        'middleware' => [],
        'checks' => [
            'database' => true,
            'cache' => true,
            'queue' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Data Filtering
    |--------------------------------------------------------------------------
    |
    | Fields to redact from request data. Values will be replaced with
    | [FILTERED] in the error report.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Maximum Payload Size
    |--------------------------------------------------------------------------
    |
    | Maximum size in bytes for the error payload. Larger payloads will be
    | truncated. Default is 64KB.
    |
    */
    'max_payload_size' => 65536,

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP timeout in seconds for sending reports when not using queues.
    |
    */
    'timeout' => 5,

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Retry settings for failed report submissions.
    |
    */
    'retry' => [
        'times' => 3,
        'sleep' => 100,
    ],
];
