<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RenderJob;
use Illuminate\Http\Request;

class RenderJobController extends Controller
{
    public function index(Request $request)
    {
        $query = RenderJob::with('user');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($search = $request->input('search')) {
            $query->where('id', 'like', "%{$search}%");
        }

        $jobs = $query->latest()->paginate(20);

        return view('admin.jobs.index', compact('jobs'));
    }

    public function show(string $id)
    {
        $job = RenderJob::with('user')->findOrFail($id);
        return view('admin.jobs.show', compact('job'));
    }

    public function destroy(string $id)
    {
        $job = RenderJob::findOrFail($id);

        // Delete the output file if exists
        if ($job->output_path && file_exists($job->output_path)) {
            @unlink($job->output_path);
        }

        $job->delete();
        return redirect('/admin/jobs')->with('success', 'Job deleted.');
    }
}
