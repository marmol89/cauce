<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

    public function job(Request $request, string $id)
    {
        return view('cauce::jobs.show', ['id' => $id]);
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
