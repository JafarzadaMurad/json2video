@extends('portal.layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="container">
        <h2 style="margin-bottom: 24px; font-size: 22px;">Welcome, {{ $user->name }}! 👋</h2>

        @if(session('api_key'))
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                🔑 <strong>Your API Key:</strong> <code class="mono"
                    style="background:#1a2035; padding:4px 8px; border-radius:4px;">{{ session('api_key') }}</code>
                <br><small class="text-muted">Save this now — it won't be shown again!</small>
            </div>
        @endif

        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Current Plan</div>
                <div class="value">{{ $user->plan?->name ?? 'None' }}</div>
                @if($user->plan_expires_at)
                    <div class="sub" style="color: {{ $user->daysUntilExpiry() <= 3 ? 'var(--red)' : 'var(--text-dim)' }}">
                        ⏳ {{ $user->daysUntilExpiry() }} days left ({{ $user->plan_expires_at->format('M j, Y') }})
                    </div>
                @elseif($user->plan?->slug === 'free')
                    <div class="sub">♾️ No expiration</div>
                @endif
                <div class="sub"><a href="/plans" style="font-size:12px">View all plans →</a></div>
            </div>
            <div class="stat-card">
                <div class="label">Render Usage</div>
                <div class="value">{{ number_format($totalRenderMinutes, 1) }} min</div>
                <div class="sub">of {{ $user->plan?->max_render_minutes ?? '∞' }} min limit</div>
            </div>
            <div class="stat-card">
                <div class="label">Max Duration</div>
                <div class="value">{{ $user->plan?->max_video_duration ?? '—' }}s</div>
                <div class="sub">per video</div>
            </div>
            <div class="stat-card">
                <div class="label">Resolution</div>
                <div class="value" style="font-size: 22px;">{{ $user->plan?->max_resolution ?? '—' }}</div>
                <div class="sub">max quality</div>
            </div>
        </div>

        @if($pendingRequests > 0)
            <div class="alert alert-warning">⏳ You have {{ $pendingRequests }} pending plan upgrade request(s). We'll contact
                you soon!</div>
        @endif

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- API Keys -->
            <div class="card">
                <div class="card-header">
                    <h3>API Keys</h3>
                </div>
                <div class="card-body" style="padding:0">
                    <table>
                        <thead>
                            <tr>
                                <th>Prefix</th>
                                <th>Label</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($user->apiKeys as $key)
                                <tr>
                                    <td class="mono">{{ $key->key_prefix }}...</td>
                                    <td>{{ $key->label }}</td>
                                    <td>{!! $key->is_active ? '<span class="badge badge-done">Active</span>' : '<span class="text-muted">Inactive</span>' !!}
                                    </td>
                                    <td class="text-sm text-muted">{{ $key->created_at->format('M j, Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted">No API keys</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Start -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Start</h3>
                </div>
                <div class="card-body">
                    <p style="font-size:13px; color: var(--text-dim); margin-bottom: 12px;">Use your API key to render
                        videos via the REST API:</p>
                    <pre class="mono"
                        style="background:var(--bg); padding:14px; border-radius:8px; overflow-x:auto; font-size:11px; line-height:1.6; color: var(--accent);">curl -X POST {{ config('app.url') }}/api/v1/movies \
              -H "X-API-Key: YOUR_API_KEY" \
              -H "Content-Type: application/json" \
              -d '{
                "resolution": "hd",
                "scenes": [{
                  "duration": 5,
                  "elements": [{
                    "type": "text",
                    "text": "Hello World!",
                    "font-size": 64,
                    "color": "#ffffff"
                  }]
                }]
              }'</pre>
                    <a href="/#docs" class="btn btn-secondary btn-sm mt-4">View full docs →</a>
                </div>
            </div>
        </div>

        <!-- Recent Jobs -->
        <div class="card mt-4">
            <div class="card-header">
                <h3>Recent Render Jobs</h3>
            </div>
            <div class="card-body" style="padding:0">
                <table>
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>Status</th>
                            <th>Resolution</th>
                            <th>Duration</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentJobs as $job)
                            <tr>
                                <td class="mono">{{ Str::limit($job->id, 14) }}</td>
                                <td><span class="badge badge-{{ $job->status }}">{{ $job->status }}</span></td>
                                <td>{{ $job->resolution }}</td>
                                <td>{{ $job->duration_seconds ? $job->duration_seconds . 's' : '—' }}</td>
                                <td>{{ $job->file_size_bytes ? round($job->file_size_bytes / 1048576, 1) . ' MB' : '—' }}</td>
                                <td class="text-sm text-muted">{{ $job->created_at->diffForHumans() }}</td>
                                <td>@if($job->output_url)<a href="{{ $job->output_url }}" target="_blank"
                                class="btn btn-sm btn-secondary">▶</a>@endif</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted" style="text-align:center; padding:32px;">No render jobs yet.
                                    <a href="/#docs">Check the docs</a> to get started!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection