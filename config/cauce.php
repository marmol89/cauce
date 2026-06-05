<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When disabled, Cauce stops tracking jobs and the dashboard becomes
    | unreachable. Useful in production environments where you want to
    | turn off monitoring without uninstalling the package.
    |
    */

    'enabled' => env('CAUCE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dashboard path & domain
    |--------------------------------------------------------------------------
    |
    | Configure where the dashboard is exposed. By default it lives under
    | /cauce. You can also bind it to a sub-domain.
    |
    */

    'path' => env('CAUCE_PATH', 'cauce'),

    'domain' => env('CAUCE_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Production access
    |--------------------------------------------------------------------------
    |
    | By default, the dashboard is only accessible in local, testing, staging,
    | or development environments. Set this to true to allow dashboard access
    | in production (you should also add authentication middleware).
    |
    */

    'allow_production' => env('CAUCE_ALLOW_PRODUCTION', false),

    'allow_production_mutate' => env('CAUCE_ALLOW_PRODUCTION_MUTATE', false),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to every dashboard request. The default `Authorize`
    | middleware gates the dashboard behind a Gate. Add your own auth
    | middleware in front of it as needed.
    |
    */

    'middleware' => [
        'web',
        \Marmol89\Cauce\Http\Middleware\Authorize::class,
        'throttle:60,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Cauce persists job records and metrics to a relational database. The
    | connection used here may be the same as the application default or a
    | dedicated one for queue observability.
    |
    */

    'storage' => [
        'database' => [
            'connection' => env('CAUCE_DB_CONNECTION', null),
            'chunk' => env('CAUCE_DB_CHUNK', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure which connections/queues are tracked. The sample rate lets
    | you reduce overhead on busy queues. The payload max size prevents
    | huge jobs from filling the database.
    |
    */

    'monitoring' => [
        'enabled_connections' => ['*'],
        'enabled_queues' => ['*'],
        'sample_rate' => env('CAUCE_SAMPLE_RATE', 1.0),
        'store_payload' => env('CAUCE_STORE_PAYLOAD', true),
        'payload_max_size' => env('CAUCE_PAYLOAD_MAX_SIZE', 65535),
        'redacted_fields' => ['password', 'token', 'secret', 'key', 'authorization', 'credential'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long Cauce keeps records before pruning. The `cauce:prune` command
    | uses these values. You can also schedule the command to run daily.
    |
    */

    'retention' => [
        'completed_hours' => env('CAUCE_RETENTION_COMPLETED_HOURS', 24),
        'failed_days' => env('CAUCE_RETENTION_FAILED_DAYS', 7),
        'metrics_days' => env('CAUCE_RETENTION_METRICS_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry defaults
    |--------------------------------------------------------------------------
    |
    | A default retry strategy applied to any job that does not specify one.
    | Leave as null to use Laravel's built-in behavior. The value must be a
    | fully qualified class name implementing the RetryStrategy contract.
    |
    */

    'retry' => [
        'default_strategy' => env('CAUCE_DEFAULT_RETRY_STRATEGY', null),
        'global_max_attempts' => env('CAUCE_GLOBAL_MAX_ATTEMPTS', null),
        'default_base' => env('CAUCE_DEFAULT_RETRY_BASE', 1),
        'default_cap' => env('CAUCE_DEFAULT_RETRY_CAP', 300),
        'default_threshold' => env('CAUCE_DEFAULT_CIRCUIT_THRESHOLD', 5),
        'default_cooldown' => env('CAUCE_DEFAULT_CIRCUIT_COOLDOWN', 60),
        'default_breaker_key' => env('CAUCE_DEFAULT_BREAKER_KEY', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Auto-refresh interval for the dashboard (Livewire polling) and rows
    | displayed per page in the jobs table.
    |
    */

    'dashboard' => [
        'refresh_seconds' => env('CAUCE_DASHBOARD_REFRESH', 5),
        'refresh_hours' => env('CAUCE_DASHBOARD_REFRESH_HOURS', 24),
        'rows_per_page' => env('CAUCE_DASHBOARD_ROWS', 25),
        'css_source' => env('CAUCE_CSS_SOURCE', 'cdn'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    |
    | Configure alerting when Cauce detects anomalies such as a spike in
    | failed jobs or a circuit breaker opening. Channels may be "log",
    | "mail", or "slack". The circuit-breaker alert can be toggled
    | independently.
    |
    */

    'alerts' => [
        'enabled' => env('CAUCE_ALERTS_ENABLED', false),
        'failed_job_threshold' => env('CAUCE_FAILED_THRESHOLD', 10),
        'failed_job_window_minutes' => env('CAUCE_FAILED_WINDOW', 5),
        'circuit_breaker_open' => env('CAUCE_ALERT_CIRCUIT_BREAKER', true),
        'channels' => ['log'],
        'mail_to' => env('CAUCE_ALERT_MAIL'),
        'slack_webhook' => env('CAUCE_ALERT_SLACK_WEBHOOK'),
        'webhook_url' => env('CAUCE_ALERT_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dead-Letter Queue
    |--------------------------------------------------------------------------
    |
    | When enabled, jobs that exhaust all retry attempts are pushed to a
    | dedicated dead-letter queue for manual inspection and replay.
    |
    */

    'dead_letter' => [
        'enabled' => env('CAUCE_DLQ_ENABLED', false),
        'connection' => env('CAUCE_DLQ_CONNECTION', null),
        'queue' => env('CAUCE_DLQ_QUEUE', 'dead-letter'),
    ],

];
