<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Marmol89\Cauce\Contracts\MetricsRepository;

class AlertManager
{
	public function __construct(
		protected MetricsRepository $metrics,
	) {}

	public function checkFailedJobThreshold(): void
	{
		if (! config('cauce.alerts.enabled')) {
			return;
		}

		$threshold = (int) config('cauce.alerts.failed_job_threshold', 10);
		$window = (int) config('cauce.alerts.failed_job_window_minutes', 5);
		$from = CarbonImmutable::now()->subMinutes($window);
		$to = CarbonImmutable::now();

		$totals = $this->metrics->totals('*', '*', $from, $to);

		if ($totals['failed'] >= $threshold) {
			$message = sprintf(
				'Cauce Alert: %d jobs failed in the last %d minutes.',
				$totals['failed'],
				$window,
			);
			$this->send($message, ['failed' => $totals['failed'], 'window_minutes' => $window]);
		}
	}

	public function notifyCircuitBreakerOpen(string $key): void
	{
		if (! config('cauce.alerts.enabled') || ! config('cauce.alerts.circuit_breaker_open')) {
			return;
		}

		$message = sprintf('Cauce Alert: Circuit breaker "%s" has opened.', $key);
		$this->send($message, ['breaker_key' => $key, 'state' => 'open']);
	}

	protected function send(string $message, array $context = []): void
	{
		$channels = config('cauce.alerts.channels', ['log']);

		foreach ($channels as $channel) {
			match ($channel) {
				'log' => Log::warning($message, $context),
				default => Log::warning($message, $context),
			};
		}
	}
}
