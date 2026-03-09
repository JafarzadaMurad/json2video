<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount('users')->orderBy('sort_order')->get();
        return view('admin.plans.index', compact('plans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:plans,slug',
            'price_monthly' => 'required|numeric|min:0',
            'max_render_minutes' => 'nullable|integer|min:1',
            'max_video_duration' => 'nullable|integer|min:1',
            'max_resolution' => 'required|string|in:sd,hd,full-hd,2k,4k',
            'rate_limit_per_minute' => 'required|integer|min:1',
            'has_watermark' => 'sometimes|boolean',
            'has_priority_queue' => 'sometimes|boolean',
            'has_webhook' => 'sometimes|boolean',
            'has_templates' => 'sometimes|boolean',
            'storage_days' => 'required|integer|min:1',
            'sort_order' => 'sometimes|integer',
        ]);

        $validated['has_watermark'] = $request->boolean('has_watermark');
        $validated['has_priority_queue'] = $request->boolean('has_priority_queue');
        $validated['has_webhook'] = $request->boolean('has_webhook');
        $validated['has_templates'] = $request->boolean('has_templates');

        Plan::create($validated);
        return redirect('/admin/plans')->with('success', 'Plan created.');
    }

    public function update(Request $request, int $id)
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price_monthly' => 'required|numeric|min:0',
            'max_render_minutes' => 'nullable|integer|min:1',
            'max_video_duration' => 'nullable|integer|min:1',
            'max_resolution' => 'required|string|in:sd,hd,full-hd,2k,4k',
            'rate_limit_per_minute' => 'required|integer|min:1',
            'has_watermark' => 'sometimes|boolean',
            'has_priority_queue' => 'sometimes|boolean',
            'has_webhook' => 'sometimes|boolean',
            'has_templates' => 'sometimes|boolean',
            'storage_days' => 'required|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['has_watermark'] = $request->boolean('has_watermark');
        $validated['has_priority_queue'] = $request->boolean('has_priority_queue');
        $validated['has_webhook'] = $request->boolean('has_webhook');
        $validated['has_templates'] = $request->boolean('has_templates');
        $validated['is_active'] = $request->boolean('is_active');

        $plan->update($validated);
        return redirect('/admin/plans')->with('success', 'Plan updated.');
    }

    public function destroy(int $id)
    {
        $plan = Plan::withCount('users')->findOrFail($id);

        if ($plan->users_count > 0) {
            return redirect()->back()->with('error', "Cannot delete plan with {$plan->users_count} active users.");
        }

        $plan->delete();
        return redirect('/admin/plans')->with('success', 'Plan deleted.');
    }
}
