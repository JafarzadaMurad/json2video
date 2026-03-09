@extends('portal.layouts.app')
@section('title', 'Video Expired')

@section('content')
    <div class="container" style="max-width: 600px; text-align: center; padding: 80px 20px;">
        <div style="font-size: 64px; margin-bottom: 20px;">⏰</div>
        <h2 style="margin-bottom: 12px;">Video Expired</h2>
        <p style="color: var(--text-dim); font-size: 15px; line-height: 1.7; margin-bottom: 24px;">
            This video's storage period has ended and the file has been deleted.
            @if($job)
                <br>It was rendered on <strong>{{ $job->completed_at?->format('M j, Y') }}</strong>
                and expired on <strong>{{ $job->expires_at?->format('M j, Y') }}</strong>.
            @endif
        </p>

        @if($job)
            <div class="card" style="text-align: left; margin-bottom: 24px;">
                <div class="card-body" style="padding: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 13px;">
                        <div><span style="color: var(--text-muted);">Job ID:</span></div>
                        <div class="mono">{{ Str::limit($job->id, 18) }}</div>
                        <div><span style="color: var(--text-muted);">Resolution:</span></div>
                        <div>{{ $job->resolution }}</div>
                        <div><span style="color: var(--text-muted);">Duration:</span></div>
                        <div>{{ $job->duration_seconds }}s</div>
                    </div>
                </div>
            </div>
            <p style="font-size: 13px; color: var(--text-dim);">
                💡 You can re-render this video by submitting the same JSON payload to the API.
            </p>
        @endif

        <div style="margin-top: 24px;">
            <a href="/" class="btn btn-primary">← Back to Home</a>
        </div>
    </div>
@endsection