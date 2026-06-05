# Changelog

All notable changes to Cauce will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Dead-Letter Queue (DLQ) with `cauce:dlq-replay` command for replaying dead-lettered jobs.
- Laravel events: `CircuitBreakerOpened`, `CircuitBreakerClosed`, `CircuitBreakerHalfOpened`, `JobRetried`, `AlertTriggered`.
- `AlertManager` now supports `mail`, `slack`, and `webhook` alert channels.
- Healthcheck endpoint at `/cauce/api/health` for uptime monitoring.
- `globalTotals()` method on `MetricsRepository` for cross-connection/queue aggregation.
- `openKeys()` static method on `CircuitBreaker` for listing open breakers.
- Cache support for `distinctTags()` to avoid expensive queries on large tables.
- Local CSS build option via `CAUCE_CSS_SOURCE=local` and `cauce-assets` publish tag.
- Configurable default retry parameters: `default_base`, `default_cap`, `default_threshold`, `default_cooldown`, `default_breaker_key`.

### Changed
- **Circuit breaker `upsertRow()` now uses atomic `upsert()` instead of `exists()` + `insert/update`** to eliminate race conditions.
- **Metrics increments on MySQL use `INSERT ... ON DUPLICATE KEY UPDATE`** for atomic counters under high concurrency.
- **`pruneCompleted()` now prunes stuck jobs without `finished_at`** based on `created_at`.
- **API routes use separate middleware group** (`api` guard + throttling) without CSRF requirement.
- **`Authorize` middleware supports both web and API guards** for token-based auth.
- **Payload truncation is progressive** (strips `data.command` before discarding).
- **Livewire components use dependency injection** instead of `app()` calls.
- **Config validation runs in all environments** including production (logs silently).
- **`RetryManager::resolveDefault()` adapts args based on strategy type** (CircuitBreaker vs backoff strategies).
- **Cauce version is read from Composer** instead of hardcoded.

### Fixed
- Migration `add_batch_chain_to_cauce_jobs` now has a complete `down()` method.
- AlertManager's `checkFailedJobThreshold` now uses `globalTotals()` instead of broken wildcard matching.
- Payload truncation no longer errors on non-array `$payload['data']`.

## [0.1.0] - 2026-01-01

### Added
- Initial release.
- Driver-agnostic queue monitoring dashboard (Livewire 3 + Alpine + Tailwind).
- Five retry strategies: Linear, Exponential, DecorrelatedJitter, Fibonacci, CircuitBreaker.
- `#[Retry]` PHP attribute for declarative configuration.
- `ApplyRetryStrategy` and `TrackJob` queue middleware.
- Event listeners for `JobQueued`, `JobProcessing`, `JobProcessed`, `JobFailed`, `JobExceptionOccurred`.
- Database-backed `JobRepository` and `MetricsRepository`.
- Artisan commands: `cauce:install`, `cauce:status`, `cauce:retry`, `cauce:prune`, `cauce:clear`.
- `viewCauce` Gate with environment-based default policy.
- Auto-publishing of config, migrations, and views.
- PHPUnit 11 test suite with Orchestra Testbench.
- GitHub Actions CI matrix (PHP 8.2-8.4 × Laravel 11-13).
