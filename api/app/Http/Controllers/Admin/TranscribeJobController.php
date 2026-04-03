<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TranscribeJob;
use Illuminate\Http\Request;

class TranscribeJobController extends Controller
{
    public function index(Request $request)
    {
        $query = TranscribeJob::with('user');

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

        return view('admin.transcribe-jobs.index', compact('jobs'));
    }

    public function show(string $id)
    {
        $job = TranscribeJob::with('user')->findOrFail($id);
        return view('admin.transcribe-jobs.show', compact('job'));
    }

    public function destroy(string $id)
    {
        $job = TranscribeJob::findOrFail($id);

        // Delete the SRT file if exists
        if ($job->srt_path && file_exists($job->srt_path)) {
            @unlink($job->srt_path);
        }

        $job->delete();
        return redirect('/admin/transcribe-jobs')->with('success', 'Transcribe job deleted.');
    }
}
