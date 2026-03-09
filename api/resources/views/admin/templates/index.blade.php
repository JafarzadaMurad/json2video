@extends('admin.layouts.app')
@section('title', 'Templates')
@section('breadcrumb', 'Management → Templates')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Templates</h3>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Public</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php $templates = \App\Models\Template::with('user')->latest()->paginate(20); @endphp
                    @forelse($templates as $tpl)
                        <tr>
                            <td class="mono">{{ Str::limit($tpl->id, 12) }}</td>
                            <td><strong>{{ $tpl->name }}</strong></td>
                            <td>{{ $tpl->user?->name ?? 'System' }}</td>
                            <td><span class="badge badge-active">{{ $tpl->category ?? 'general' }}</span></td>
                            <td>{!! $tpl->is_public ? '<span class="text-accent">✓</span>' : '—' !!}</td>
                            <td class="text-muted text-sm">{{ $tpl->created_at->format('M j, Y') }}</td>
                            <td>
                                <form method="POST" action="/admin/templates/{{ $tpl->id }}" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete?')">✕</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted" style="text-align: center; padding: 40px;">No templates yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection