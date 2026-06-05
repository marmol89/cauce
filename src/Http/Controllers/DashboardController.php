<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Marmol89\Cauce\Contracts\JobRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('cauce::dashboard');
    }

    public function jobs(Request $request)
    {
        return view('cauce::jobs.index');
    }

    public function job(Request $request, string $id, JobRepository $jobs)
    {
        $job = $jobs->find($id);

        if ($job === null) {
            throw new NotFoundHttpException("Cauce job [{$id}] not found.");
        }

        return view('cauce::jobs.show', ['job' => $job]);
    }

    public function jobAction(Request $request, string $id, JobRepository $jobs)
    {
        $action = (string) $request->input('_action');

        return match ($action) {
            'retry' => tap(redirect()->route('cauce.jobs.show', $id), function () use ($jobs, $id) {
                $jobs->retry($id);
            })->with('status', 'Job re-queued.'),
            'delete' => tap(redirect()->route('cauce.failed'), fn () => $jobs->delete($id))
                ->with('status', 'Job deleted.'),
            default => redirect()->route('cauce.jobs.show', $id),
        };
    }

    public function failed(Request $request)
    {
        return view('cauce::failed.index');
    }

    public function metrics(Request $request)
    {
        return view('cauce::metrics.index');
    }

    public function queues(Request $request)
    {
        return view('cauce::queues.index');
    }
}
