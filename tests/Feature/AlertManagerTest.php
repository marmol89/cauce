<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Events\AlertTriggered;
use Marmol89\Cauce\Support\AlertManager;
use Marmol89\Cauce\Tests\TestCase;

class AlertManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cauce.alerts.enabled', true);
        config()->set('cauce.alerts.failed_job_threshold', 5);
        config()->set('cauce.alerts.failed_job_window_minutes', 5);
    }

    public function test_does_not_alert_when_disabled(): void
    {
        config()->set('cauce.alerts.enabled', false);
        Event::fake();

        $alerts = app(AlertManager::class);
        $alerts->checkFailedJobThreshold();

        Event::assertNotDispatched(AlertTriggered::class);
    }

    public function test_alerts_when_failed_threshold_exceeded(): void
    {
        $metrics = app(MetricsRepository::class);
        $metrics->increment('test', 'default', 'failed', 5.0);
        Event::fake();

        $alerts = app(AlertManager::class);
        $alerts->checkFailedJobThreshold();

        Event::assertDispatched(AlertTriggered::class);
    }

    public function test_does_not_alert_when_below_threshold(): void
    {
        $metrics = app(MetricsRepository::class);
        $metrics->increment('test', 'default', 'failed', 2.0);
        Event::fake();

        $alerts = app(AlertManager::class);
        $alerts->checkFailedJobThreshold();

        Event::assertNotDispatched(AlertTriggered::class);
    }

    public function test_notify_circuit_breaker_open_dispatches_event(): void
    {
        config()->set('cauce.alerts.circuit_breaker_open', true);
        Event::fake();

        $alerts = app(AlertManager::class);
        $alerts->notifyCircuitBreakerOpen('payment-api');

        Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $event) {
            return $event->type === 'circuit_breaker_open'
                && str_contains($event->message, 'payment-api');
        });
    }

    public function test_notify_circuit_breaker_closed_dispatches_event(): void
    {
        config()->set('cauce.alerts.circuit_breaker_open', true);
        Event::fake();

        $alerts = app(AlertManager::class);
        $alerts->notifyCircuitBreakerClosed('payment-api');

        Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $event) {
            return $event->type === 'circuit_breaker_closed'
                && str_contains($event->message, 'payment-api');
        });
    }
}
