@extends('admin.layouts.app')
@section('title', 'Transcribe Job Detail')
@section('breadcrumb', 'Management → Transcribe Jobs → Detail')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h3>Job: <span class="mono">{{ $job->id }}</span></h3>
            <span class="badge badge-{{ $job->status }}">{{ $job->status }}</span>
        </div>
        <div class="card-body padded">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">Status</div>
                    <div class="value"><span class="badge badge-{{ $job->status }}">{{ $job->status }}</span></div>
                </div>
                <div class="stat-card">
                    <div class="label">Source Type</div>
                    <div class="value">{{ $job->src_type ?? '—' }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">Language</div>
                    <div class="value">{{ $job->language ?? 'auto' }}</div>
                    @if($job->language_confidence)
                        <div class="sub">Confidence: {{ round($job->language_confidence * 100) }}%</div>
                    @endif
                </div>
                <div class="stat-card">
                    <div class="label">Segments</div>
                    <div class="value">{{ $job->segments ?? '—' }}</div>
                </div>
            </div>

            <table>
                <tr>
                    <td class="text-muted" style="width:160px;">Job ID</td>
                    <td class="mono">{{ $job->id }}</td>
                </tr>
                <tr>
                    <td class="text-muted">User</td>
                    <td>
                        @if($job->user)
                            <a href="/admin/users/{{ $job->user->id }}">{{ $job->user->name }}</a>
                            ({{ $job->user->email }})
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Source URL</td>
                    <td><a href="{{ $job->src_url }}" target="_blank" style="word-break:break-all;">{{ $job->src_url }}</a>
                    </td>
                </tr>
                @if($job->srt_url)
                    <tr>
                        <td class="text-muted">SRT URL</td>
                        <td><a href="{{ $job->srt_url }}" target="_blank" class="btn btn-primary btn-sm">📄 Download SRT</a>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="text-muted">Worker</td>
                    <td class="mono">{{ $job->worker_id ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Created</td>
                    <td>{{ $job->created_at->format('Y-m-d H:i:s') }} ({{ $job->created_at->diffForHumans() }})</td>
                </tr>
                @if($job->started_at)
                    <tr>
                        <td class="text-muted">Started</td>
                        <td>{{ $job->started_at }}</td>
                    </tr>
                @endif
                @if($job->completed_at)
                    <tr>
                        <td class="text-muted">Completed</td>
                        <td>{{ $job->completed_at }}</td>
                    </tr>
                @endif
                @if($job->expires_at)
                    <tr>
                        <td class="text-muted">Expires</td>
                        <td>{{ $job->expires_at }}</td>
                    </tr>
                @endif
                @if($job->error)
                    <tr>
                        <td class="text-muted">Error</td>
                        <td class="text-red">{{ $job->error }}</td>
                    </tr>
                @endif
            </table>

            <div class="mt-4 flex gap-2">
                <a href="/admin/transcribe-jobs" class="btn btn-secondary">← Back</a>
                <form method="POST" action="/admin/transcribe-jobs/{{ $job->id }}" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this job?')">🗑️
                        Delete</button>
                </form>
            </div>
        </div>
    </div>
@endsection