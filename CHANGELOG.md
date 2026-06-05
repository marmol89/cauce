# Changelog

All notable changes to Cauce will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
