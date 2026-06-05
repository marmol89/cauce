<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Events\AlertTriggered;

class AlertManager
{
    public function __construct(
        protected MetricsRepository $metrics,
    ) {
    }

    public function checkFailedJobThreshold(): void
    {
        if (! config('cauce.alerts.enabled')) {
            return;
        }

        $threshold = (int) config('cauce.alerts.failed_job_threshold', 10);
        $window = (int) config('cauce.alerts.failed_job_window_minutes', 5);

        $cacheKey = 'cauce:alert_dedupe:failed_job_threshold';
        if (Cache::get($cacheKey)) {
            return;
        }

        $from = CarbonImmutable::now()->subMinutes($window);
        $to = CarbonImmutable::now();

        $totals = $this->metrics->globalTotals($from, $to);

        if ($totals['failed'] >= $threshold) {
            $message = sprintf(
                'Cauce Alert: %d jobs failed in the last %d minutes.',
                $totals['failed'],
                $window,
            );
            $this->send('failed_job_threshold', $message, ['failed' => $totals['failed'], 'window_minutes' => $window]);

            Cache::put($cacheKey, true, max(60, $window * 60));
        }
    }

    public function notifyCircuitBreakerOpen(string $key): void
    {
        if (! config('cauce.alerts.enabled') || ! config('cauce.alerts.circuit_breaker_open')) {
            return;
        }

        $message = sprintf('Cauce Alert: Circuit breaker "%s" has opened.', $key);
        $this->send('circuit_breaker_open', $message, ['breaker_key' => $key, 'state' => 'open']);
    }

    public function notifyCircuitBreakerClosed(string $key): void
    {
        if (! config('cauce.alerts.enabled') || ! config('cauce.alerts.circuit_breaker_open')) {
            return;
        }

        $message = sprintf('Cauce Alert: Circuit breaker "%s" has closed.', $key);
        $this->send('circuit_breaker_closed', $message, ['breaker_key' => $key, 'state' => 'closed']);
    }

    protected function send(string $type, string $message, array $context = []): void
    {
        AlertTriggered::dispatch($type, $message, $context);

        $channels = (array) config('cauce.alerts.channels', ['log']);

        foreach ($channels as $channel) {
            match ($channel) {
                'log' => Log::warning($message, $context),
                'mail' => $this->sendMail($message, $context),
                'slack' => $this->sendSlack($message, $context),
                'webhook' => $this->sendWebhook($message, $context),
                default => Log::warning($message, $context),
            };
        }
    }

    protected function sendMail(string $message, array $context): void
    {
        $to = config('cauce.alerts.mail_to');
        if (empty($to)) {
            return;
        }

        try {
            Mail::raw($message, function ($mail) use ($to, $context) {
                $mail->to($to)
                    ->subject('Cauce Alert: ' . ($context['type'] ?? ''));
            });
        } catch (\Throwable $e) {
            Log::warning('Cauce: failed to send mail alert', ['error' => $e->getMessage()]);
        }
    }

    protected function sendSlack(string $message, array $context): void
    {
        $webhook = config('cauce.alerts.slack_webhook');
        if (empty($webhook)) {
            return;
        }

        try {
            $http = app(\Illuminate\Http\Client\Factory::class);
            $http->post($webhook, ['text' => $message]);
        } catch (\Throwable $e) {
            Log::warning('Cauce: failed to send slack alert', ['error' => $e->getMessage()]);
        }
    }

    protected function sendWebhook(string $message, array $context): void
    {
        $url = config('cauce.alerts.webhook_url');
        if (empty($url)) {
            return;
        }

        try {
            $http = app(\Illuminate\Http\Client\Factory::class);
            $http->post($url, ['message' => $message, 'context' => $context]);
        } catch (\Throwable $e) {
            Log::warning('Cauce: failed to send webhook alert', ['error' => $e->getMessage()]);
        }
    }
}
