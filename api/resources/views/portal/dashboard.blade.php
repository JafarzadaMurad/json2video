@extends('portal.layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="container">
        <h2 style="margin-bottom: 24px; font-size: 22px;">Welcome, {{ $user->name }}! 👋</h2>

        @if(session('api_key'))
            <div class="alert alert-warning" style="margin-bottom: 20px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    🔑 <strong>Your API Key:</strong> <code class="mono" id="api-key-text"
                        style="background:#1a2035; padding:4px 8px; border-radius:4px;">{{ session('api_key') }}</code>
                    <br><small class="text-muted">Save this now — it won't be shown again!</small>
                </div>
                <button onclick="copyRaw(document.getElementById('api-key-text').textContent)" class="btn btn-sm btn-secondary" style="white-space:nowrap; margin-left:auto;">📋 Copy</button>
            </div>
        @endif

        @if(session('new_api_key'))
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div>
                        🔑 <strong>New API Key:</strong>
                        <code class="mono" id="new-key-text"
                            style="background:#1a2035; padding:4px 8px; border-radius:4px; word-break:break-all;">{{ session('new_api_key') }}</code>
                    </div>
                    <button onclick="copyRaw(document.getElementById('new-key-text').textContent)" class="btn btn-sm btn-secondary" style="white-space:nowrap; margin-left:auto;">📋 Copy Key</button>
                </div>
                <small class="text-muted" style="display:block; margin-top:8px;">⚠️ Save this key now! It won't be shown again.</small>
            </div>
        @endif

        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Current Plan</div>
                <div class="value">{{ $user->plan?->name ?? 'None' }}</div>
                @if($user->plan_expires_at)
                    @if($user->daysUntilExpiry() <= 3)
                        <small style="color:#f87171;">⏳ {{ $user->daysUntilExpiry() }} days left</small>
                    @else
                        <small class="text-muted">⏳ {{ $user->daysUntilExpiry() }} days left</small>
                    @endif
                @else
                    <small class="text-muted">∞ No expiration</small>
                @endif
                <a href="/plans" style="font-size:12px; color: var(--accent);">View all plans →</a>
            </div>
            <div class="stat-card">
                <div class="label">Render Usage</div>
                <div class="value">{{ number_format($totalRenderMinutes, 1) }} min</div>
                <small class="text-muted">of {{ $user->plan?->max_render_minutes ?? 10 }} min limit</small>
            </div>
            <div class="stat-card">
                <div class="label">Max Duration</div>
                <div class="value">{{ $user->plan?->max_video_duration ?? 180 }}s</div>
                <small class="text-muted">per video</small>
            </div>
            <div class="stat-card">
                <div class="label">Resolution</div>
                <div class="value">{{ $user->plan?->max_resolution ?? 'hd' }}</div>
                <small class="text-muted">max quality</small>
            </div>
        </div>

        @if($pendingRequests > 0)
            <div class="alert alert-warning">⏳ You have {{ $pendingRequests }} pending plan upgrade request(s). We'll contact
                you soon!</div>
        @endif

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- API Keys -->
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3>API Keys</h3>
                    <form method="POST" action="/api-keys" style="display:flex; gap:6px; align-items:center;">
                        @csrf
                        <input type="text" name="label" placeholder="Key label" style="padding:4px 8px; font-size:12px; width:100px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text);">
                        <button type="submit" class="btn btn-sm btn-primary" style="padding:4px 10px; font-size:11px;">+ New Key</button>
                    </form>
                </div>
                <div class="card-body" style="padding:0">
                    <table>
                        <thead>
                            <tr>
                                <th>Prefix</th>
                                <th>Label</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($user->apiKeys as $key)
                                <tr>
                                    <td class="mono">{{ $key->key_prefix }}...</td>
                                    <td>{{ $key->label }}</td>
                                    <td>{!! $key->is_active ? '<span class="badge badge-done">Active</span>' : '<span class="text-muted">Inactive</span>' !!}</td>
                                    <td>
                                        <form method="POST" action="/api-keys/{{ $key->id }}" style="display:inline;" onsubmit="return confirm('Delete this API key?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" style="background:none; border:none; color:#f87171; cursor:pointer; font-size:12px;">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted">No API keys — create one above</td>
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
                    <div style="position:relative;">
                        <button onclick="copyText('curl-code')" class="btn btn-sm btn-secondary"
                            style="position:absolute; top:8px; right:8px; font-size:11px; padding:4px 10px; z-index:1;">📋
                            Copy</button>
                        <pre class="mono" id="curl-code"
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
                    </div>
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

    <script>
    function copyText(elementId) {
        const el = document.getElementById(elementId);
        const text = el.innerText || el.textContent;
        copyRaw(text);
    }

    function copyRaw(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => showCopied());
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showCopied();
        }
    }

    function showCopied() {
        const btn = event.target.closest('button');
        if (!btn) return;
        const original = btn.innerHTML;
        btn.innerHTML = '✅';
        setTimeout(() => btn.innerHTML = original, 1500);
    }
    </script>
@endsection