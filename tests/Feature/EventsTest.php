<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Marmol89\Cauce\Events\CircuitBreakerClosed;
use Marmol89\Cauce\Events\CircuitBreakerHalfOpened;
use Marmol89\Cauce\Events\CircuitBreakerOpened;
use Marmol89\Cauce\Events\JobRetried;
use Marmol89\Cauce\Tests\TestCase;

class EventsTest extends TestCase
{
    public function test_job_retried_dispatches_event(): void
    {
        \Illuminate\Support\Facades\Event::fake([JobRetried::class]);

        config()->set('queue.connections.test-sqs', [
            'driver' => 'null',
        ]);

        $jobs = app(\Marmol89\Cauce\Contracts\JobRepository::class);

        $id = $jobs->recordQueued([
            'uuid' => 'retry-event-uuid',
            'connection' => 'test-sqs',
            'queue' => 'default',
            'name' => 'RetryEventJob',
            'status' => 'failed',
            'payload' => json_encode([
                'displayName' => 'RetryEventJob',
                'data' => ['commandName' => \Marmol89\Cauce\Tests\Fixtures\TestJob::class],
                'uuid' => 'retry-event-uuid',
            ]),
        ]);

        $jobs->retry($id);

        \Illuminate\Support\Facades\Event::assertDispatched(JobRetried::class, function (JobRetried $event) use ($id) {
            return $event->cauceId === $id && $event->jobName === 'RetryEventJob';
        });
    }

    public function test_circuit_breaker_events_dispatched(): void
    {
        \Illuminate\Support\Facades\Event::fake([
            CircuitBreakerOpened::class,
            CircuitBreakerHalfOpened::class,
            CircuitBreakerClosed::class,
        ]);

        config()->set('cauce.alerts.enabled', false);

        $breaker = new \Marmol89\Cauce\Retry\CircuitBreaker(
            threshold: 2,
            cooldown: 1,
            maxAttempts: 3,
            key: 'event-test',
        );

        $breaker->recordFailure();
        \Illuminate\Support\Facades\Event::assertNotDispatched(CircuitBreakerOpened::class);

        $breaker->recordFailure();
        \Illuminate\Support\Facades\Event::assertDispatched(CircuitBreakerOpened::class);

        $breaker->reset();
    }
}
