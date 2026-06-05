<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)">
    <img src="https://raw.githubusercontent.com/marmol89/cauce/main/.github/cauce-logo.svg" alt="Cauce" width="360">
  </picture>
</p>

<p align="center">
  <a href="https://packagist.org/packages/marmol89/cauce"><img src="https://img.shields.io/packagist/v/marmol89/cauce?label=release&color=6366f1" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/marmol89/cauce"><img src="https://img.shields.io/packagist/php-v/marmol89/cauce?color=10b981" alt="PHP Version"></a>
  <a href="https://packagist.org/packages/marmol89/cauce"><img src="https://img.shields.io/badge/laravel-11%20|%2012%20|%2013-f43f5e" alt="Laravel"></a>
  <a href="https://github.com/marmol89/cauce/actions/workflows/tests.yml"><img src="https://github.com/marmol89/cauce/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/marmol89/cauce"><img src="https://img.shields.io/packagist/l/marmol89/cauce?color=6366f1" alt="License"></a>
  <a href="https://packagist.org/packages/marmol89/cauce"><img src="https://img.shields.io/packagist/dt/marmol89/cauce?color=f59e0b" alt="Downloads"></a>
</p>

<br>

> **Cauce** *(noun, Spanish)* — riverbed, channel, conduit.
>
> A driver-agnostic queue monitoring dashboard and advanced retry strategies for Laravel — no Redis required.

<br>

## Why Cauce?

Laravel Horizon is excellent but **locked to Redis**. If you use **database**, **SQS**, **Beanstalkd**, **RabbitMQ**, or any other queue driver, you're out of luck for observability.

Cauce gives you a **real-time dashboard** and **5 battle-tested retry strategies** that work with every Laravel queue driver. One `composer require`, zero driver restrictions.

---

## Features

<table>
<tr><td width="50%">

### 🔭 Observability
- Live dashboard (Livewire 3 + Alpine + Tailwind)
- Real-time throughput, runtime & success rate
- Per-connection and per-queue filtering
- Failed job browser with one-click retry
- CLI status overview (`cauce:status`)

</td><td width="50%">

### 🛡️ Resilience
- 5 retry strategies with configurable backoffs
- Circuit breaker with automatic state transitions
- Dead Letter Queue for exhausted jobs
- Payload redaction for sensitive fields
- Configurable alerting (log, mail, Slack, webhook)

</td></tr>
</table>

### Retry Strategies

| Strategy | Delay formula | Best for |
|---|---|---|
| `LinearBackoff` | `base × attempt` | Predictable, simple retries |
| `ExponentialBackoff` | `base × 2^attempt` | Most APIs (AWS-style backoff) |
| `DecorrelatedJitter` | `random(base, min(cap, base × 2^attempt))` | Thundering herd prevention |
| `FibonacciBackoff` | `F(attempt) × base` | Gradual, gentle growth |
| `CircuitBreaker` | *state machine* | Unstable external services |

---

## Requirements

- **PHP** 8.2+
- **Laravel** 11.x / 12.x / 13.x
- **Database** — any Laravel-supported engine (MySQL, PostgreSQL, SQLite)

---

## Installation

```bash
composer require marmol89/cauce
```

Then install assets and run migrations:

```bash
php artisan cauce:install
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=cauce-config
```

The dashboard is now available at `/cauce`.

---

## Quick start

### 1. Track jobs (zero config)

Cauce automatically tracks every job flowing through Laravel's queue — no code changes needed.

### 2. Apply a retry strategy

Use the `#[Retry]` PHP 8 attribute on any `ShouldQueue` job:

```php
use Marmol89\Cauce\Attributes\Retry;
use Marmol89\Cauce\Retry\ExponentialBackoff;

#[Retry(strategy: ExponentialBackoff::class, max: 5, base: 2, cap: 300)]
class SendInvoiceEmail implements ShouldQueue
{
    public function handle(): void
    {
        // Job logic
    }
}
```

Or via Laravel queue middleware:

```php
public function middleware(): array
{
    return [
        new \Marmol89\Cauce\Middleware\ApplyRetryStrategy(
            new \Marmol89\Cauce\Retry\ExponentialBackoff(base: 2, cap: 300, maxAttempts: 5)
        ),
    ];
}
```

### 3. Circuit breaker for external services

```php
use Marmol89\Cauce\Attributes\Retry;
use Marmol89\Cauce\Retry\CircuitBreaker;

#[Retry(
    strategy: CircuitBreaker::class,
    threshold: 5,       // failures before opening
    cooldown: 60,       // seconds before half-open test
    key: 'payment-api'  // unique per service
)]
class ProcessPayment implements ShouldQueue
{
    // ...
}
```

The circuit breaker transitions through three states:

```
CLOSED ──(5 failures)──▶ OPEN ──(cooldown)──▶ HALF-OPEN
   ▲                                              │
   └────────────(success)─────────────────────────┘
```

---

## Dashboard access control

By default, Cauce allows access in `local`, `testing`, `staging`, and `development`. In production, you control access via environment variables and a customizable Gate.

### Environment-based

```env
# .env
CAUCE_ALLOW_PRODUCTION=true
CAUCE_ALLOW_PRODUCTION_MUTATE=true
```

### Authentication requirement

```env
CAUCE_REQUIRE_AUTH=true
```

When enabled, Cauce denies access to unauthenticated users even in development environments.

### Custom authorization

Define a Gate in your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewCauce', function ($user = null) {
    return $user !== null && $user->hasRole('admin');
});
```

Or use the `authorization_callback` config:

```php
// config/cauce.php
'authorization_callback' => fn ($user) => $user->isAdmin(),
```

Add Laravel's `auth` middleware to `config/cauce.php` to require login:

```php
'middleware' => [
    'web',
    'auth',
    \Marmol89\Cauce\Http\Middleware\Authorize::class,
    'throttle:60,1',
],
```

---

## Configuration reference

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `CAUCE_ENABLED` | `true` | Global on/off switch |
| `CAUCE_PATH` | `cauce` | Dashboard URL path |
| `CAUCE_ALLOW_PRODUCTION` | `false` | Allow dashboard in production |
| `CAUCE_ALLOW_PRODUCTION_MUTATE` | `false` | Allow retry/delete in production |
| `CAUCE_REQUIRE_AUTH` | `false` | Require authenticated user |
| `CAUCE_DB_CONNECTION` | `null` | Dedicated DB connection |
| `CAUCE_SAMPLE_RATE` | `1.0` | Sampling 0.0–1.0 |
| `CAUCE_STORE_PAYLOAD` | `true` | Store job payloads |
| `CAUCE_PAYLOAD_MAX_SIZE` | `65535` | Max payload bytes |
| `CAUCE_RETENTION_COMPLETED_HOURS` | `24` | Completed jobs retention |
| `CAUCE_RETENTION_FAILED_DAYS` | `7` | Failed jobs retention |
| `CAUCE_RETENTION_METRICS_DAYS` | `30` | Metrics retention |
| `CAUCE_DEFAULT_RETRY_STRATEGY` | `null` | Default retry class |
| `CAUCE_GLOBAL_MAX_ATTEMPTS` | `null` | Global max attempts |
| `CAUCE_DEFAULT_CIRCUIT_THRESHOLD` | `5` | Circuit breaker threshold |
| `CAUCE_DEFAULT_CIRCUIT_COOLDOWN` | `60` | Circuit breaker cooldown (s) |
| `CAUCE_ALERTS_ENABLED` | `false` | Alert system |
| `CAUCE_ALERT_MAIL` | `null` | Alert email |
| `CAUCE_ALERT_SLACK_WEBHOOK` | `null` | Slack webhook URL |
| `CAUCE_ALERT_WEBHOOK` | `null` | Generic webhook URL |
| `CAUCE_DLQ_ENABLED` | `false` | Dead Letter Queue |
| `CAUCE_DLQ_QUEUE` | `dead-letter` | DLQ queue name |
| `CAUCE_LOG_CHANNEL` | `null` | Dedicated log channel |

---

## Commands

| Command | Description |
|---|---|
| `cauce:install` | Publish assets and run migrations |
| `cauce:status` | CLI overview of all queues and jobs |
| `cauce:retry {id}` | Re-queue a failed job by its Cauce ID |
| `cauce:prune` | Purge old records per retention config |
| `cauce:clear` | Wipe data. `--jobs`, `--metrics`, `--breakers`, `--all` |
| `cauce:dlq-replay` | Replay jobs from the Dead Letter Queue |
| `cauce:alert` | Trigger alert evaluation manually |

### Scheduled pruning

```php
// routes/console.php
Schedule::command('cauce:prune')->daily();
```

---

## API

Cauce exposes a REST API under `/cauce/api/v1` for programmatic access. The health endpoint at `/cauce/api/health` is unauthenticated for uptime monitoring.

| Method | Endpoint | Auth |
|---|---|---|
| `GET` | `/api/v1/status` | Gate |
| `GET` | `/api/v1/jobs` | Gate |
| `GET` | `/api/v1/jobs/{id}` | Gate |
| `POST` | `/api/v1/jobs/{id}/retry` | `mutateCauce` |
| `DELETE` | `/api/v1/jobs/{id}` | `mutateCauce` |
| `GET` | `/api/v1/failed` | Gate |
| `GET` | `/api/v1/metrics` | Gate |
| `GET` | `/api/health` | None |

---

## Testing

```bash
composer test        # Run the full suite
composer testdox     # Human-readable output
composer test-coverage  # With coverage report
```

Cauce has **124 tests** across 25 test classes covering unit and feature scenarios, with a CI matrix spanning PHP 8.2–8.4 × Laravel 11–13.

---

## Security

- Sensitive fields (`password`, `token`, `secret`, `key`, `authorization`, `credential`) are **automatically redacted** from stored payloads.
- Payload storage can be **disabled entirely** via `CAUCE_STORE_PAYLOAD=false`.
- Circuit breaker uses **atomic database operations** to prevent race conditions.
- Rate limiting is applied to all web (`60/min`) and API (`120/min`) routes.
- `roave/security-advisories` blocks installation of packages with known CVEs.

To report a vulnerability, open an issue on [GitHub](https://github.com/marmol89/cauce/issues).

---

## License

MIT © [marmol89](https://github.com/marmol89)
