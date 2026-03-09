@extends('admin.layouts.app')
@section('title', 'Job: ' . Str::limit($job->id, 16))
@section('breadcrumb', 'Jobs → Detail')

@section('content')
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="card">
            <div class="card-header">
                <h3>Job Info</h3>
            </div>
            <div class="card-body padded">
                <table style="font-size: 14px;">
                    <tr>
                        <td class="text-muted" style="width: 140px;">Job ID</td>
                        <td class="mono">{{ $job->id }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td><span class="badge badge-{{ $job->status }}">{{ $job->status }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">User</td>
                        <td><a href="/admin/users/{{ $job->user_id }}">{{ $job->user?->name ?? '—' }}</a></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Resolution</td>
                        <td>{{ $job->resolution }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Quality</td>
                        <td>{{ $job->quality }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Progress</td>
                        <td>{{ $job->progress }}%</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Duration</td>
                        <td>{{ $job->duration_seconds ?? '—' }}s</td>
                    </tr>
                    <tr>
                        <td class="text-muted">File Size</td>
                        <td>{{ $job->file_size_bytes ? round($job->file_size_bytes / 1048576, 2) . ' MB' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Worker</td>
                        <td class="mono">{{ $job->worker_id ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created</td>
                        <td>{{ $job->created_at->format('Y-m-d H:i:s') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Started</td>
                        <td>{{ $job->started_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Completed</td>
                        <td>{{ $job->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Expires</td>
                        <td>{{ $job->expires_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    </tr>
                </table>

                @if($job->output_url)
                    <div style="margin-top: 16px;">
                        <a href="{{ $job->output_url }}" target="_blank" class="btn btn-primary btn-sm">▶ Open Video</a>
                    </div>
                @endif

                @if($job->error_message)
                    <div class="alert alert-error" style="margin-top: 16px;">
                        <strong>{{ $job->error_code }}:</strong> {{ $job->error_message }}
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Payload JSON</h3>
            </div>
            <div class="card-body padded">
                <pre
                    style="background: var(--bg); padding: 16px; border-radius: 8px; font-size: 12px; overflow-x: auto; max-height: 500px; font-family: 'SF Mono', Consolas, monospace; color: var(--accent); line-height: 1.5;">{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    </div>
@endsection