<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Models\ApiKey;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('plan')->withCount('renderJobs');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($planId = $request->input('plan_id')) {
            $query->where('plan_id', $planId);
        }

        $users = $query->latest()->paginate(20);
        $plans = Plan::orderBy('sort_order')->get();

        return view('admin.users.index', compact('users', 'plans'));
    }

    public function show(int $id)
    {
        $user = User::with([
            'plan',
            'apiKeys',
            'renderJobs' => function ($q) {
                $q->latest()->take(20);
            }
        ])->findOrFail($id);

        return view('admin.users.show', compact('user'));
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'plan_id' => 'nullable|exists:plans,id',
            'plan_expires_at' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
            'is_admin' => 'sometimes|boolean',
        ]);

        $validated['is_admin'] = $request->boolean('is_admin');
        $validated['plan_expires_at'] = $request->input('plan_expires_at') ?: null;
        $user->update($validated);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return redirect()->back()->with('error', 'Cannot delete the last admin user.');
        }

        $user->delete();
        return redirect('/admin/users')->with('success', 'User deleted.');
    }
}
