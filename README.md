# Cauce

> Driver-agnostic queue monitoring and advanced retry strategies for Laravel.

Cauce gives you a clean dashboard and battle-tested retry strategies for the Laravel queue system, **without the Redis-only restriction** of Horizon. It works with **database, Redis, SQS, Beanstalkd, RabbitMQ, or any other driver** Laravel supports.

## Features

- 📊 **Live dashboard** built with Livewire 3 + Alpine + Tailwind
- 🔌 **Multi-driver** — works with all Laravel queue drivers (unlike Horizon)
- 🔁 **5 retry strategies** — Linear, Exponential, Decorrelated Jitter, Fibonacci, and Circuit Breaker
- 📈 **Real-time metrics** — throughput, runtime, success rate
- 💀 **Failed job browser** with one-click retry
- 🧹 **Automatic pruning** of old records
- 🎛️ **Configurable per-connection/queue** tracking

## Requirements

- PHP 8.2+
- Laravel 11.x, 12.x, or 13.x

## Installation

```bash
composer require marmol89/cauce
```

Publish the assets and run the migrations:

```bash
php artisan cauce:install
php artisan migrate
```

Optionally, publish the config:

```bash
php artisan vendor:publish --tag=cauce-config
```

## Configuration

The dashboard is available at `/cauce` by default. You can change the path, restrict it to specific environments, and configure retention in `config/cauce.php`.

### Dashboard access control

By default, the dashboard is only accessible in `local`, `testing`, `staging`, and `development` environments. To allow access in production, set the config or env variable:

```bash
# .env
CAUCE_ALLOW_PRODUCTION=true
CAUCE_PATH=cauce
```

To add authentication, extend the middleware array in `config/cauce.php`:

```php
'middleware' => [
    'web',
    \Marmol89\Cauce\Http\Middleware\Authorize::class,
    'auth',              // Require authentication
    'throttle:60,1',     // Rate limit: 60 requests per minute
],
```

You can also customize the Gate in your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewCauce', function ($user = null) {
    return $user !== null && $user->hasRole('admin');
});
```

### Circuit breaker

The `CircuitBreaker` strategy prevents repeated calls to failing external services. Configure it per-job:

```php
#[Retry(
    strategy: CircuitBreaker::class,
    threshold: 5,     // Failures before opening (default: 5)
    cooldown: 60,     // Seconds before trying again (default: 60)
    key: 'payment-api' // Unique key per service
)]
class ProcessPayment implements ShouldQueue
{
    // ...
}
```

The breaker transitions through three states:
- **Closed** — normal operation, requests pass through
- **Open** — failures exceeded threshold, all requests blocked
- **Half-open** — cooldown expired, allow one request to test the service

### Payload redaction

Cauce automatically redacts sensitive fields (passwords, tokens, secrets) from stored job payloads. Customize the field list in `config/cauce.php`:

```php
'monitoring' => [
    'redacted_fields' => ['password', 'token', 'secret', 'key', 'authorization'],
],
```

## Quick start

### Tracking jobs

Cauce automatically tracks every job that flows through Laravel's queue system. No extra configuration needed.

### Retry strategies

Apply a retry strategy to a job using the `#[Retry]` attribute:

```php
use Marmol89\Cauce\Attributes\Retry;
use Marmol89\Cauce\Retry\ExponentialBackoff;

#[Retry(strategy: ExponentialBackoff::class, max: 5, base: 2, cap: 300)]
class SendInvoiceEmail implements ShouldQueue
{
    // ...
}
```

Or via middleware (Laravel-style):

```php
public function middleware(): array
{
    return [
        new \Marmol89\Cauce\Middleware\ApplyRetryStrategy(
            new \Marmol89\Cauce\Retry\ExponentialBackoff(base: 2, cap: 300, max: 5)
        ),
    ];
}
```

### Available strategies

| Strategy | Best for |
|---|---|
| `LinearBackoff` | Predictable, simple retries |
| `ExponentialBackoff` | Most APIs (AWS-style) |
| `DecorrelatedJitter` | Avoiding thundering herd |
| `FibonacciBackoff` | Gradual growth |
| `CircuitBreaker` | Unstable external services |

## Commands

| Command | Description |
|---|---|
| `php artisan cauce:install` | Publish assets and run migrations |
| `php artisan cauce:status` | CLI overview of queue status |
| `php artisan cauce:retry {id}` | Retry a failed job. Use `--queue=` and `--connection=` to re-queue on a different queue/connection. |
| `php artisan cauce:prune` | Clean up old records based on retention config |
| `php artisan cauce:clear` | Wipe stored data. Use `--jobs`, `--metrics`, `--breakers`, or `--all`. |

### Scheduling prune

Add to `routes/console.php` or your scheduler:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('cauce:prune')->daily();
```

### Clearing specific data

```bash
# Clear only job records
php artisan cauce:clear --jobs --force

# Clear only metrics
php artisan cauce:clear --metrics --force

# Clear circuit breaker state
php artisan cauce:clear --breakers --force

# Clear everything
php artisan cauce:clear --all --force
```

## Troubleshooting

**Dashboard returns 403 in production**
Set `CAUCE_ALLOW_PRODUCTION=true` in your `.env` file, or customize the `viewCauce` Gate.

**Jobs are not appearing in the dashboard**
Verify `CAUCE_ENABLED` is not set to `false`. Cauce auto-discovers package migrations, but if you're having issues run `php artisan vendor:publish --tag=cauce-migrations` then `php artisan migrate`.

**Circuit breaker state not persisting**
Circuit breaker state is stored in the `cauce_circuit_breakers` database table. Ensure migrations have been run and the configured database connection is accessible.

**Dashboard performance with many jobs**
Configure retention settings to keep the jobs table lean:
```php
'retention' => [
    'completed_hours' => 24,   // Keep completed jobs for 24h
    'failed_days' => 7,        // Keep failed jobs for 7 days
    'metrics_days' => 30,      // Keep metrics for 30 days
],
```
Then schedule `cauce:prune` to run daily.

**Sensitive data in payloads**
Cauce redacts common sensitive field names by default. To add more, configure `redacted_fields` in `config/cauce.php`. To disable payload storage entirely, set `CAUCE_STORE_PAYLOAD=false`.

## Why Cauce?

Laravel Horizon is excellent but tightly coupled to Redis. If you run queues on database, SQS, Beanstalkd, or any other driver, you're out of luck for monitoring. **Cauce is driver-agnostic** and adds sophisticated retry strategies on top of any queue system.

## License

MIT © [marmol89](https://github.com/marmol89)
