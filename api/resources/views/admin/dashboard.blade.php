@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Overview')

@section('content')
    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">Total Users</div>
            <div class="value">{{ $stats['total_users'] }}</div>
        </div>
        <div class="stat-card">
            <div class="label">Completed Renders</div>
            <div class="value">{{ $stats['completed_jobs'] }}</div>
            <div class="sub">{{ $stats['failed_jobs'] }} failed</div>
        </div>
        <div class="stat-card">
            <div class="label">Active Queue</div>
            <div class="value">{{ $stats['queued_jobs'] + $stats['processing_jobs'] }}</div>
            <div class="sub">{{ $stats['queued_jobs'] }} queued · {{ $stats['processing_jobs'] }} processing</div>
        </div>
        <div class="stat-card">
            <div class="label">Storage Used</div>
            <div class="value">{{ $stats['total_storage_mb'] }} MB</div>
            <div class="sub">Avg render: {{ $stats['avg_render_time'] }}s</div>
        </div>
        <div class="stat-card">
            <div class="label">🎤 Transcriptions</div>
            <div class="value">{{ $transcribeStats['total'] }}</div>
            <div class="sub">{{ $transcribeStats['done'] }} done · {{ $transcribeStats['processing'] }} processing ·
                {{ $transcribeStats['failed'] }} failed</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
        <!-- Recent Render Jobs -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Render Jobs</h3>
                <a href="/admin/jobs" class="btn btn-secondary btn-sm">View All →</a>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Size</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentJobs as $job)
                            <tr>
                                <td class="mono">{{ Str::limit($job->id, 12) }}</td>
                                <td>{{ $job->user?->name ?? '—' }}</td>
                                <td><span class="badge badge-{{ $job->status }}">{{ $job->status }}</span></td>
                                <td>{{ $job->file_size_bytes ? round($job->file_size_bytes / 1048576, 1) . ' MB' : '—' }}</td>
                                <td class="text-muted text-sm">{{ $job->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Plan Breakdown -->
        <div class="card">
            <div class="card-header">
                <h3>Plan Distribution</h3>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Users</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($planBreakdown as $plan)
                            <tr>
                                <td>
                                    <strong>{{ $plan->name }}</strong>
                                    @if(!$plan->is_active)<span class="badge badge-inactive">off</span>@endif
                                </td>
                                <td>{{ $plan->users_count }}</td>
                                <td>${{ $plan->price_monthly }}/mo</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Transcribe Jobs -->
    <div class="card">
        <div class="card-header">
            <h3>🎤 Recent Transcribe Jobs</h3>
            <a href="/admin/transcribe-jobs" class="btn btn-secondary btn-sm">View All →</a>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Language</th>
                        <th>Segments</th>
                        <th>SRT</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentTranscribeJobs as $job)
                        <tr>
                            <td class="mono"><a href="/admin/transcribe-jobs/{{ $job->id }}">{{ Str::limit($job->id, 12) }}</a>
                            </td>
                            <td>{{ $job->user?->name ?? '—' }}</td>
                            <td><span class="badge badge-{{ $job->status }}">{{ $job->status }}</span></td>
                            <td>{{ $job->language ?? '—' }}</td>
                            <td>{{ $job->segments ?? '—' }}</td>
                            <td>
                                @if($job->srt_url)
                                    <a href="{{ $job->srt_url }}" target="_blank">📄</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-muted text-sm">{{ $job->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center; padding:20px; color:var(--text-muted);">No transcribe jobs
                                yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection