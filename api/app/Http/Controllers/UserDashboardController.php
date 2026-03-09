<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanRequest;
use App\Models\RenderJob;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $user->load('plan', 'apiKeys');

        $recentJobs = RenderJob::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        // Usage stats
        $totalRenderMinutes = RenderJob::where('user_id', $user->id)
            ->where('status', 'done')
            ->sum('duration_seconds') / 60;

        $pendingRequests = PlanRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        return view('portal.dashboard', compact(
            'user',
            'recentJobs',
            'totalRenderMinutes',
            'pendingRequests'
        ));
    }

    public function plans()
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $currentPlan = auth()->user()->plan;
        $pendingRequest = PlanRequest::where('user_id', auth()->id())
            ->where('status', 'pending')
            ->first();

        return view('portal.plans', compact('plans', 'currentPlan', 'pendingRequest'));
    }

    public function requestPlan(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'phone' => 'required|string|max:20',
            'message' => 'nullable|string|max:500',
        ]);

        // Check for existing pending request
        $existing = PlanRequest::where('user_id', auth()->id())
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return redirect()->back()->with('error', 'You already have a pending plan request.');
        }

        PlanRequest::create([
            'user_id' => auth()->id(),
            'plan_id' => $validated['plan_id'],
            'phone' => $validated['phone'],
            'message' => $validated['message'] ?? null,
        ]);

        // Update user phone if not set
        if (!auth()->user()->phone) {
            auth()->user()->update(['phone' => $validated['phone']]);
        }

        return redirect()->back()->with('success', 'Plan upgrade request submitted! We will contact you soon.');
    }
}
