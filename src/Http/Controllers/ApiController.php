<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Retry\CircuitBreaker;

class ApiController extends Controller
{
    public function __construct()
    {
    }

	public function status(Request $request, JobRepository $jobs): JsonResponse
	{
		$hours = (int) $request->input('hours', 24);

		return response()->json([
			'data' => [
				'by_status' => $jobs->countsByStatus($hours),
				'by_connection' => $jobs->countsByConnection($hours),
				'by_queue' => $jobs->countsByQueue($hours),
			],
		]);
	}

	public function jobs(Request $request, JobRepository $jobs): JsonResponse
	{
		$perPage = (int) $request->input('per_page', 25);
		$filters = [];

		if ($request->filled('status')) {
			$filters['status'] = (string) $request->input('status');
		}

		if ($request->filled('connection')) {
			$filters['connection'] = (string) $request->input('connection');
		}

		if ($request->filled('queue')) {
			$filters['queue'] = (string) $request->input('queue');
		}

		if ($request->filled('search')) {
			$filters['name'] = (string) $request->input('search');
		}

		$paginator = $jobs->paginate($filters, $perPage);

		return response()->json([
			'data' => $paginator->items(),
			'meta' => [
				'current_page' => $paginator->currentPage(),
				'last_page' => $paginator->lastPage(),
				'per_page' => $paginator->perPage(),
				'total' => $paginator->total(),
			],
		]);
	}

	public function job(Request $request, string $id, JobRepository $jobs): JsonResponse
	{
		$job = $jobs->find($id);

		if ($job === null) {
			return response()->json([
				'message' => "Cauce job [{$id}] not found.",
			], 404);
		}

		return response()->json([
			'data' => $job,
		]);
	}

	public function retry(Request $request, string $id, JobRepository $jobs): JsonResponse
	{
		$job = $jobs->find($id);

		if ($job === null) {
			return response()->json([
				'message' => "Cauce job [{$id}] not found.",
			], 404);
		}

		$success = $jobs->retry($id);

		if (! $success) {
			return response()->json([
				'message' => 'Job could not be retried.',
			], 422);
		}

		return response()->json([
			'data' => $jobs->find($id),
			'message' => 'Job re-queued.',
		]);
	}

	public function delete(Request $request, string $id, JobRepository $jobs): JsonResponse
	{
		$job = $jobs->find($id);

		if ($job === null) {
			return response()->json([
				'message' => "Cauce job [{$id}] not found.",
			], 404);
		}

		$jobs->delete($id);

		return response()->json([
			'message' => 'Job deleted.',
		]);
	}

	public function failed(Request $request, JobRepository $jobs): JsonResponse
	{
		$perPage = (int) $request->input('per_page', 25);

		$paginator = $jobs->failed($perPage);

		return response()->json([
			'data' => $paginator->items(),
			'meta' => [
				'current_page' => $paginator->currentPage(),
				'last_page' => $paginator->lastPage(),
				'per_page' => $paginator->perPage(),
				'total' => $paginator->total(),
			],
		]);
	}

    public function metrics(Request $request, MetricsRepository $metrics): JsonResponse
    {
        $hours = (int) $request->input('hours', 24);
        $from = CarbonImmutable::now()->subHours($hours);
        $to = CarbonImmutable::now();

        return response()->json([
            'data' => $metrics->totals('*', '*', $from, $to),
        ]);
    }

    public function health(Request $request, JobRepository $jobs): JsonResponse
    {
        $status = 'ok';
        $checks = [];

        try {
            $jobs->countsByStatus(1);
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'error: ' . $e->getMessage();
            $status = 'degraded';
        }

        try {
            $openBreakers = CircuitBreaker::openKeys();
            if (count($openBreakers) > 0) {
                $checks['circuit_breakers_open'] = $openBreakers;
                $status = $status === 'ok' ? 'warning' : $status;
            } else {
                $checks['circuit_breakers_open'] = [];
            }
        } catch (\Throwable $e) {
            $checks['circuit_breakers'] = 'error: ' . $e->getMessage();
        }

        $httpCode = match ($status) {
            'ok' => 200,
            'warning' => 200,
            'degraded' => 503,
            default => 500,
        };

        return response()->json([
            'status' => $status,
            'checks' => $checks,
        ], $httpCode);
    }
}
