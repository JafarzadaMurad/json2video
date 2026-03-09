<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\RenderJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Stats
        $stats = [
            'total_users' => User::count(),
            'total_jobs' => RenderJob::count(),
            'completed_jobs' => RenderJob::where('status', 'done')->count(),
            'failed_jobs' => RenderJob::where('status', 'failed')->count(),
            'queued_jobs' => RenderJob::where('status', 'queued')->count(),
            'processing_jobs' => RenderJob::where('status', 'processing')->count(),
            'total_storage_mb' => round(RenderJob::where('status', 'done')->sum('file_size_bytes') / 1048576, 1),
            'avg_render_time' => round(
                RenderJob::where('status', 'done')
                    ->whereNotNull('started_at')
                    ->whereNotNull('completed_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_time')
                    ->value('avg_time') ?? 0
            ),
        ];

        // Recent jobs
        $recentJobs = RenderJob::with('user')
            ->latest()
            ->take(10)
            ->get();

        // Jobs per day (last 14 days)
        $jobsPerDay = RenderJob::where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, status')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        // Plans breakdown
        $planBreakdown = Plan::withCount('users')
            ->orderBy('sort_order')
            ->get();

        return view('admin.dashboard', compact('stats', 'recentJobs', 'jobsPerDay', 'planBreakdown'));
    }
}
