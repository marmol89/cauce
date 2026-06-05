# Contributing

Thank you for your interest in contributing to Cauce! Please take a moment to review this document.

## Reporting bugs

Open a GitHub issue with:
- A descriptive title
- A minimal reproduction (steps, expected, actual)
- Laravel and PHP versions
- Relevant config (driver, retention, sample_rate)

## Pull requests

1. Fork the repo and create a feature branch (`git checkout -b feature/my-feature`).
2. Write code and tests. New features must include PHPUnit coverage.
3. Run the test suite locally:
   ```bash
   composer install
   vendor/bin/phpunit
   ```
4. Update `CHANGELOG.md` and the README when applicable.
5. Open a PR against `main`. PRs must pass CI (matrix PHP × Laravel) and at least one maintainer review.

## Coding style

- Strict types: `declare(strict_types=1);` at the top of every PHP file.
- PSR-12 with tabs as default indentation.
- Prefer explicit return types on public methods.
- No unused imports.

## Architecture guidelines

- `Contracts/` interfaces — do not change signatures without a major version bump.
- `Repositories/` — keep the public surface area small; new methods should be added to the contract first.
- `Listeners/` — never throw out of `handle()`; use `Log::warning()` for soft failures.
- `Retry/` — implement `RetryStrategy` for new strategies. Keep `delay()` deterministic where possible.
- `Http/Livewire/` — components are auto-registered in the service provider under `cauce-` prefix.
