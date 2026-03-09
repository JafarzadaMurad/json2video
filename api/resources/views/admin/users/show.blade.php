@extends('admin.layouts.app')
@section('title', $user->name)
@section('breadcrumb', 'Users → ' . $user->name)

@section('content')
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Edit User -->
        <div class="card">
            <div class="card-header">
                <h3>Edit User</h3>
            </div>
            <div class="card-body padded">
                <form method="POST" action="/admin/users/{{ $user->id }}">
                    @csrf @method('PUT')
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="{{ $user->name }}" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="{{ $user->email }}" required>
                    </div>
                    <div class="form-group">
                        <label>Plan</label>
                        <select name="plan_id">
                            <option value="">No Plan</option>
                            @foreach(\App\Models\Plan::orderBy('sort_order')->get() as $plan)
                                <option value="{{ $plan->id }}" {{ $user->plan_id == $plan->id ? 'selected' : '' }}>
                                    {{ $plan->name }} (${{ $plan->price_monthly }}/mo)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Plan Expires At</label>
                        <input type="date" name="plan_expires_at" value="{{ $user->plan_expires_at?->format('Y-m-d') }}">
                        @if($user->plan_expires_at)
                            <small class="text-muted">{{ $user->daysUntilExpiry() }} days left</small>
                        @else
                            <small class="text-muted">No expiration (Free plan)</small>
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="{{ $user->phone }}" placeholder="No phone">
                    </div>
                    <div class="form-group">
                        <label>Video Storage Days Override</label>
                        <input type="number" name="storage_days_override" value="{{ $user->storage_days_override }}"
                            placeholder="{{ $user->plan?->storage_days ?? 3 }} (plan default)" min="1" max="365">
                        <small class="text-muted">
                            Plan default: {{ $user->plan?->storage_days ?? 3 }} days.
                            Leave empty to use plan default.
                        </small>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_admin" value="1" id="is_admin" {{ $user->is_admin ? 'checked' : '' }}>
                        <label for="is_admin">Administrator</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>

                <form method="POST" action="/admin/users/{{ $user->id }}" style="margin-top: 20px;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user?')">Delete
                        User</button>
                </form>
            </div>
        </div>

        <!-- User Info -->
        <div>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>API Keys</h3>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Prefix</th>
                                <th>Label</th>
                                <th>Active</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($user->apiKeys as $key)
                                <tr>
                                    <td class="mono">{{ $key->key_prefix }}...</td>
                                    <td>{{ $key->label }}</td>
                                    <td>{!! $key->is_active ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Off</span>' !!}
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

            <div class="card">
                <div class="card-header">
                    <h3>Recent Jobs</h3>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Size</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($user->renderJobs as $job)
                                <tr>
                                    <td class="mono"><a href="/admin/jobs/{{ $job->id }}">{{ Str::limit($job->id, 12) }}</a>
                                    </td>
                                    <td><span class="badge badge-{{ $job->status }}">{{ $job->status }}</span></td>
                                    <td>{{ $job->file_size_bytes ? round($job->file_size_bytes / 1048576, 1) . ' MB' : '—' }}
                                    </td>
                                    <td class="text-sm text-muted">{{ $job->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted">No jobs yet</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection