@extends('admin.layouts.app')
@section('title', 'Transcribe Jobs')
@section('breadcrumb', 'Management → Transcribe Jobs')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>{{ $jobs->total() }} Transcribe Jobs</h3>
            <form method="GET" class="form-inline">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by Job ID..."
                    style="width: 240px;">
                <select name="status" style="width: 140px;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    @foreach(['queued', 'processing', 'done', 'failed'] as $s)
                        <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            </form>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Language</th>
                        <th>Segments</th>
                        <th>Created</th>
                        <th>SRT</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                        <tr>
                            <td class="mono"><a href="/admin/transcribe-jobs/{{ $job->id }}">{{ Str::limit($job->id, 14) }}</a>
                            </td>
                            <td>{{ $job->user?->name ?? '—' }}</td>
                            <td><span class="badge badge-{{ $job->status }}">{{ $job->status }}</span></td>
                            <td>
                                <span
                                    class="badge badge-{{ $job->src_type === 'video' ? 'processing' : 'queued' }}">{{ $job->src_type ?? '—' }}</span>
                            </td>
                            <td>{{ $job->language ?? '—' }}</td>
                            <td>{{ $job->segments ?? '—' }}</td>
                            <td class="text-muted text-sm">{{ $job->created_at->diffForHumans() }}</td>
                            <td>
                                @if($job->srt_url)
                                    <a href="{{ $job->srt_url }}" target="_blank" class="btn btn-secondary btn-sm">📄 SRT</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="/admin/transcribe-jobs/{{ $job->id }}" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete?')">✕</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align:center; padding:32px; color:var(--text-muted);">No transcribe jobs
                                found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination">
        {{ $jobs->withQueryString()->links('admin.partials.pagination') }}
    </div>
@endsection