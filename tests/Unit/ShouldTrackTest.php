<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Tests\Unit;

use Marmol89\Cauce\Support\ShouldTrack;
use Marmol89\Cauce\Tests\TestCase;

class ShouldTrackTest extends TestCase
{
	public function test_connection_with_wildcard_matches_any_connection(): void
	{
		config(['cauce.monitoring.enabled_connections' => ['*']]);

		$this->assertTrue(ShouldTrack::connection('database'));
		$this->assertTrue(ShouldTrack::connection('redis'));
		$this->assertTrue(ShouldTrack::connection(null));
	}

	public function test_connection_with_specific_name_matches(): void
	{
		config(['cauce.monitoring.enabled_connections' => ['redis']]);

		$this->assertTrue(ShouldTrack::connection('redis'));
	}

	public function test_connection_with_specific_name_does_not_match(): void
	{
		config(['cauce.monitoring.enabled_connections' => ['redis']]);

		$this->assertFalse(ShouldTrack::connection('database'));
	}

	public function test_connection_with_null_name_returns_false_without_wildcard(): void
	{
		config(['cauce.monitoring.enabled_connections' => ['redis']]);

		$this->assertFalse(ShouldTrack::connection(null));
	}

	public function test_queue_with_wildcard_matches_any_queue(): void
	{
		config(['cauce.monitoring.enabled_queues' => ['*']]);

		$this->assertTrue(ShouldTrack::queue('default'));
		$this->assertTrue(ShouldTrack::queue('high'));
		$this->assertTrue(ShouldTrack::queue(null));
	}

	public function test_queue_with_specific_name_matches(): void
	{
		config(['cauce.monitoring.enabled_queues' => ['high']]);

		$this->assertTrue(ShouldTrack::queue('high'));
	}

	public function test_queue_with_specific_name_does_not_match(): void
	{
		config(['cauce.monitoring.enabled_queues' => ['high']]);

		$this->assertFalse(ShouldTrack::queue('default'));
	}

	public function test_sample_rate_of_1_0_always_returns_true(): void
	{
		config(['cauce.monitoring.sample_rate' => 1.0]);

		for ($i = 0; $i < 100; $i++) {
			$this->assertTrue(ShouldTrack::sampled());
		}
	}

	public function test_sample_rate_of_0_0_always_returns_false(): void
	{
		config(['cauce.monitoring.sample_rate' => 0.0]);

		for ($i = 0; $i < 100; $i++) {
			$this->assertFalse(ShouldTrack::sampled());
		}
	}
}
