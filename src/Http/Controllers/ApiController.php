<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;

class ApiController extends Controller
{
	public function __construct()
	{
		Gate::authorize('viewCauce');
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
}
