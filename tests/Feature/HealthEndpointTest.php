<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Marmol89\Cauce\Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cauce.middleware', ['web']);
    }

    protected function defineRoutes($router): void
    {
        $router->get('cauce/api/health', [
            \Marmol89\Cauce\Http\Controllers\ApiController::class,
            'health',
        ])->middleware(['web']);
    }

    public function test_health_returns_200_when_db_is_ok(): void
    {
        $response = $this->get('/cauce/api/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('checks.database', 'ok');
    }

    public function test_health_includes_circuit_breaker_check(): void
    {
        $response = $this->get('/cauce/api/health');

        $response->assertOk();
        $this->assertArrayHasKey('circuit_breakers_open', $response->json('checks'));
    }
}
