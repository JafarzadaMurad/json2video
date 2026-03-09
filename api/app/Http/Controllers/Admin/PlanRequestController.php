<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanRequest;
use Illuminate\Http\Request;

class PlanRequestController extends Controller
{
    public function index()
    {
        $requests = PlanRequest::with(['user', 'plan'])
            ->latest()
            ->paginate(20);

        return view('admin.plan-requests.index', compact('requests'));
    }

    public function approve(int $id)
    {
        $planRequest = PlanRequest::findOrFail($id);

        // Change user's plan + set 30-day expiration
        $planRequest->user->update([
            'plan_id' => $planRequest->plan_id,
            'plan_expires_at' => now()->addDays(30),
        ]);

        // Mark as approved
        $planRequest->update(['status' => 'approved']);

        return redirect()->back()->with('success', "Plan upgraded for {$planRequest->user->name} — expires " . now()->addDays(30)->format('M j, Y'));
    }

    public function reject(Request $request, int $id)
    {
        $planRequest = PlanRequest::findOrFail($id);
        $planRequest->update([
            'status' => 'rejected',
            'admin_note' => $request->input('admin_note'),
        ]);

        return redirect()->back()->with('success', 'Request rejected.');
    }
}
