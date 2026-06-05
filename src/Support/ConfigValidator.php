<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Support;

use Illuminate\Support\Facades\Log;

class ConfigValidator
{
	protected array $errors = [];

	public function validate(): bool
	{
		$this->errors = [];

		$sampleRate = (float) config('cauce.monitoring.sample_rate', 1.0);
		if ($sampleRate < 0.0 || $sampleRate > 1.0) {
			$this->errors[] = 'cauce.monitoring.sample_rate must be between 0.0 and 1.0.';
		}

		$completedHours = (int) config('cauce.retention.completed_hours', 24);
		if ($completedHours < 1) {
			$this->errors[] = 'cauce.retention.completed_hours must be at least 1.';
		}

		$failedDays = (int) config('cauce.retention.failed_days', 7);
		if ($failedDays < 1) {
			$this->errors[] = 'cauce.retention.failed_days must be at least 1.';
		}

		$metricsDays = (int) config('cauce.retention.metrics_days', 30);
		if ($metricsDays < 1) {
			$this->errors[] = 'cauce.retention.metrics_days must be at least 1.';
		}

		$defaultStrategy = config('cauce.retry.default_strategy');
		if ($defaultStrategy !== null && ! is_a($defaultStrategy, \Marmol89\Cauce\Contracts\RetryStrategy::class, true)) {
			$this->errors[] = sprintf(
				'cauce.retry.default_strategy "%s" must implement RetryStrategy.',
				(string) $defaultStrategy,
			);
		}

		$refreshSeconds = (int) config('cauce.dashboard.refresh_seconds', 5);
		if ($refreshSeconds < 1) {
			$this->errors[] = 'cauce.dashboard.refresh_seconds must be at least 1.';
		}

		foreach ($this->errors as $error) {
			Log::warning('Cauce config validation: ' . $error);
		}

		return $this->errors === [];
	}

	public function errors(): array
	{
		return $this->errors;
	}
}
