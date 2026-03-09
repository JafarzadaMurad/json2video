@extends('admin.layouts.app')
@section('title', 'Render Jobs')
@section('breadcrumb', 'Management → Render Jobs')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>{{ $jobs->total() }} Jobs</h3>
            <form method="GET" class="form-inline">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by Job ID..."
                    style="width: 240px;">
                <select name="status" style="width: 140px;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    @foreach(['queued', 'processing', 'done', 'failed', 'cancelled', 'expired'] as $s)
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
                        <th>Resolution</th>
                        <th>Duration</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($jobs as $job)
                        <tr>
                            <td class="mono"><a href="/admin/jobs/{{ $job->id }}">{{ Str::limit($job->id, 14) }}</a></td>
                            <td>{{ $job->user?->name ?? '—' }}</td>
                            <td><span class="badge badge-{{ $job->status }}">{{ $job->status }}</span></td>
                            <td>{{ $job->resolution }}</td>
                            <td>{{ $job->duration_seconds ? $job->duration_seconds . 's' : '—' }}</td>
                            <td>{{ $job->file_size_bytes ? round($job->file_size_bytes / 1048576, 1) . ' MB' : '—' }}</td>
                            <td class="text-muted text-sm">{{ $job->created_at->diffForHumans() }}</td>
                            <td>
                                <form method="POST" action="/admin/jobs/{{ $job->id }}" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete?')">✕</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination">
        {{ $jobs->withQueryString()->links('admin.partials.pagination') }}
    </div>
@endsection