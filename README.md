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
| `php artisan cauce:retry {id}` | Retry a failed job |
| `php artisan cauce:prune` | Clean up old records |
| `php artisan cauce:clear` | Wipe stored data |

## Why Cauce?

Laravel Horizon is excellent but tightly coupled to Redis. If you run queues on database, SQS, Beanstalkd, or any other driver, you're out of luck for monitoring. **Cauce is driver-agnostic** and adds sophisticated retry strategies on top of any queue system.

## License

MIT © [marmol89](https://github.com/marmol89)
