<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Http\Middleware\Authorize;
use Marmol89\Cauce\Tests\TestCase;

class AuthorizeMiddlewareTest extends TestCase
{
	protected function defineEnvironment($app): void
	{
		parent::defineEnvironment($app);

		$app['config']->set('cauce.middleware', [
			'web',
			Authorize::class,
		]);
	}

	protected function tearDown(): void
	{
		if (isset($this->app)) {
			$this->app['env'] = 'testing';
		}

		parent::tearDown();
	}

	public function test_dashboard_accessible_in_allowed_environment(): void
	{
		$this->get('/cauce')->assertOk();
	}

	public function test_dashboard_blocked_in_production_without_allow_production(): void
	{
		$this->app['env'] = 'production';
		$this->app['config']->set('cauce.allow_production', false);

		$this->get('/cauce')->assertForbidden();
	}

	public function test_dashboard_accessible_in_production_with_allow_production(): void
	{
		$this->app['env'] = 'production';
		$this->app['config']->set('cauce.allow_production', true);

		$this->get('/cauce')->assertOk();
	}

	public function test_job_action_retry(): void
	{
		Queue::fake();

		$id = app(JobRepository::class)->recordQueued([
			'uuid' => (string) Str::uuid(),
			'connection' => 'sync',
			'queue' => 'default',
			'name' => \stdClass::class,
			'status' => 'failed',
			'payload' => json_encode([
				'data' => ['commandName' => \stdClass::class],
			]),
		]);

		$this->withoutMiddleware([
			\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
		]);

		$this->post("/cauce/jobs/{$id}", ['_action' => 'retry'])
			->assertRedirect(route('cauce.jobs.show', $id))
			->assertSessionHas('status', 'Job re-queued.');

		$job = app(JobRepository::class)->find($id);
		$this->assertNotNull($job);
		$this->assertSame('queued', $job->status);
	}

	public function test_job_action_delete(): void
	{
		$id = app(JobRepository::class)->recordQueued([
			'uuid' => (string) Str::uuid(),
			'connection' => 'sync',
			'queue' => 'default',
			'name' => 'TestJob',
			'status' => 'failed',
		]);

		$this->withoutMiddleware([
			\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
		]);

		$this->post("/cauce/jobs/{$id}", ['_action' => 'delete'])
			->assertRedirect(route('cauce.failed'))
			->assertSessionHas('status', 'Job deleted.');

		$this->assertNull(app(JobRepository::class)->find($id));
	}

	public function test_job_action_invalid_action(): void
	{
		$id = app(JobRepository::class)->recordQueued([
			'uuid' => (string) Str::uuid(),
			'connection' => 'sync',
			'queue' => 'default',
			'name' => 'TestJob',
			'status' => 'failed',
		]);

		$this->withoutMiddleware([
			\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
		]);

		$this->post("/cauce/jobs/{$id}", ['_action' => 'invalid'])
			->assertRedirect(route('cauce.jobs.show', $id))
			->assertSessionMissing('status');
	}
}
