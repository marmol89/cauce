<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Marmol89\Cauce\Facades\Cauce;
use Marmol89\Cauce\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_renders(): void
    {
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);

        $this->get('/cauce')->assertOk();
        $this->get('/cauce/jobs')->assertOk();
        $this->get('/cauce/failed')->assertOk();
        $this->get('/cauce/metrics')->assertOk();
        $this->get('/cauce/queues')->assertOk();
    }

    public function test_facade_version(): void
    {
        $this->assertSame('0.1.0', Cauce::version());
    }

    public function test_facade_path(): void
    {
        $this->assertSame('cauce', Cauce::path());
    }

    public function test_facade_is_enabled(): void
    {
        $this->assertTrue(Cauce::isEnabled());
    }
}
