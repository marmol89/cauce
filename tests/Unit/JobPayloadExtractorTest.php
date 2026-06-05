<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobQueued;
use Marmol89\Cauce\Support\JobPayloadExtractor;
use Marmol89\Cauce\Tests\TestCase;
use Mockery;

class JobPayloadExtractorTest extends TestCase
{
	private JobPayloadExtractor $extractor;

	protected function setUp(): void
	{
		parent::setUp();

		$this->extractor = new JobPayloadExtractor();
	}

	public function test_from_queued_returns_correct_structure(): void
	{
		$job = Mockery::mock();

		$payload = json_encode([
			'uuid' => '8b4e15a0-3f5a-4b7c-9d2e-1a0b3c4d5e6f',
			'displayName' => 'App\\Jobs\\TestJob',
			'data' => ['foo' => 'bar'],
		]);

		$event = new JobQueued('redis', 'high', 'job-id-123', $job, $payload, 0);

		$result = $this->extractor->fromQueued($event);

		$this->assertArrayHasKey('uuid', $result);
		$this->assertArrayHasKey('connection', $result);
		$this->assertArrayHasKey('queue', $result);
		$this->assertArrayHasKey('name', $result);
		$this->assertArrayHasKey('payload', $result);
		$this->assertArrayHasKey('tags', $result);
		$this->assertArrayHasKey('queued_at', $result);
		$this->assertArrayHasKey('status', $result);

		$this->assertSame('8b4e15a0-3f5a-4b7c-9d2e-1a0b3c4d5e6f', $result['uuid']);
		$this->assertSame('redis', $result['connection']);
		$this->assertSame('high', $result['queue']);
		$this->assertSame('App\\Jobs\\TestJob', $result['name']);
		$this->assertSame('queued', $result['status']);
	}

	public function test_from_queued_uses_uuid_from_payload(): void
	{
		$job = Mockery::mock();

		$uuid = 'ab5f26b1-4g6b-5c8d-0e3f-2b1c4d5e6f7a';

		$payload = json_encode([
			'uuid' => $uuid,
			'displayName' => 'App\\Jobs\\SendEmail',
			'data' => ['to' => 'test@example.com'],
		]);

		$event = new JobQueued('sqs', 'default', null, $job, $payload, 0);

		$result = $this->extractor->fromQueued($event);

		$this->assertSame($uuid, $result['uuid']);
	}

	public function test_from_queued_resolves_name_when_no_display_name(): void
	{
		$job = new class {
			public function resolveName(): string
			{
				return 'App\\Jobs\\ResolvedNameJob';
			}
		};

		$payload = json_encode([
			'uuid' => 'c7d8e9f0-1234-5678-90ab-cdef12345678',
			'data' => ['order_id' => 42],
		]);

		$event = new JobQueued('beanstalkd', 'low', 'job-id-456', $job, $payload, 30);

		$result = $this->extractor->fromQueued($event);

		$this->assertSame('App\\Jobs\\ResolvedNameJob', $result['name']);
	}

	public function test_from_queued_uses_default_queue_when_null(): void
	{
		$job = Mockery::mock();

		$payload = json_encode([
			'uuid' => 'd890efa1-2345-6789-0abc-def123456789',
			'displayName' => 'App\\Jobs\\ProcessOrder',
			'data' => ['order_id' => 42],
		]);

		$event = new JobQueued('beanstalkd', null, 'job-id-456', $job, $payload, 30);

		$result = $this->extractor->fromQueued($event);

		$this->assertSame('default', $result['queue']);
	}

	public function test_from_queued_includes_tags(): void
	{
		$job = new class {
			public function tags(): array
			{
				return ['import', 'csv', 'user:42'];
			}
		};

		$payload = json_encode([
			'uuid' => 'ef01ab23-4567-8901-cdef-123456789abc',
			'displayName' => 'App\\Jobs\\ImportCsv',
			'data' => ['file' => 'data.csv'],
		]);

		$event = new JobQueued('redis', 'imports', null, $job, $payload, 0);

		$result = $this->extractor->fromQueued($event);

		$this->assertSame(['import', 'csv', 'user:42'], $result['tags']);
	}

	public function test_from_queued_returns_null_payload_when_store_payload_disabled(): void
	{
		config()->set('cauce.monitoring.store_payload', false);

		$job = Mockery::mock();

		$payload = json_encode([
			'uuid' => 'f012ab34-5678-901c-def1-23456789abcd',
			'displayName' => 'App\\Jobs\\TestJob',
			'data' => ['secret' => 'value'],
		]);

		$event = new JobQueued('redis', 'default', null, $job, $payload, 0);

		$result = $this->extractor->fromQueued($event);

		$this->assertNull($result['payload']);
	}

	public function test_from_queued_returns_null_payload_when_exceeds_max_size(): void
	{
		config()->set('cauce.monitoring.payload_max_size', 20);

		$job = Mockery::mock();

		$payload = json_encode([
			'uuid' => '1234ab56-7890-cdef-1234-56789abcdef0',
			'displayName' => 'App\\Jobs\\LargePayloadJob',
			'data' => str_repeat('x', 500),
		]);

		$event = new JobQueued('redis', 'default', null, $job, $payload, 0);

		$result = $this->extractor->fromQueued($event);

		$this->assertNull($result['payload']);
	}

	public function test_from_failed_returns_correct_structure(): void
	{
		$job = Mockery::mock();

		$exception = new \RuntimeException('Something went wrong');

		$event = new JobFailed('redis', $job, $exception);

		$result = $this->extractor->fromFailed($event);

		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('failed_at', $result);
		$this->assertArrayHasKey('exception', $result);

		$this->assertSame('failed', $result['status']);
		$this->assertStringContainsString('RuntimeException: Something went wrong', $result['exception']);
	}

	public function test_from_exception_returns_correct_structure(): void
	{
		$job = Mockery::mock();

		$exception = new \InvalidArgumentException('Invalid input provided');

		$event = new JobExceptionOccurred('sqs', $job, $exception);

		$result = $this->extractor->fromException($event);

		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('exception', $result);

		$this->assertSame('retrying', $result['status']);
		$this->assertStringContainsString('InvalidArgumentException: Invalid input provided', $result['exception']);
	}

	public function test_normalize_exception_returns_null_for_null_exception(): void
	{
		$reflection = new \ReflectionMethod(JobPayloadExtractor::class, 'normalizeException');

		$result = $reflection->invoke($this->extractor, null);

		$this->assertNull($result);
	}

	public function test_normalize_exception_formats_with_previous_exceptions(): void
	{
		$previous = new \LogicException('Inner logic failure');
		$exception = new \RuntimeException('Wrapper error', 0, $previous);

		$reflection = new \ReflectionMethod(JobPayloadExtractor::class, 'normalizeException');

		$result = $reflection->invoke($this->extractor, $exception);

		$this->assertStringContainsString('LogicException: Inner logic failure', $result);
		$this->assertStringContainsString('#0', $result);
	}

	public function test_resolve_tags_returns_empty_when_no_tags_method(): void
	{
		$job = Mockery::mock();

		$reflection = new \ReflectionMethod(JobPayloadExtractor::class, 'resolveTags');

		$result = $reflection->invoke($this->extractor, $job);

		$this->assertSame([], $result);
	}

	public function test_resolve_tags_extracts_and_filters_tags(): void
	{
		$job = new class {
			public function tags(): array
			{
				return ['email', '', 'notification', ''];
			}
		};

		$reflection = new \ReflectionMethod(JobPayloadExtractor::class, 'resolveTags');

		$result = $reflection->invoke($this->extractor, $job);

		$this->assertSame(['email', 'notification'], $result);
	}

	protected function tearDown(): void
	{
		Mockery::close();

		parent::tearDown();
	}
}
