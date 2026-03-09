@extends('admin.layouts.app')
@section('title', 'Users')
@section('breadcrumb', 'Management → Users')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h3>{{ $users->total() }} Users</h3>
            <form method="GET" class="form-inline">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email..."
                    style="width: 240px;">
                <select name="plan_id" style="width: 160px;" onchange="this.form.submit()">
                    <option value="">All Plans</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" {{ request('plan_id') == $plan->id ? 'selected' : '' }}>{{ $plan->name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            </form>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Jobs</th>
                        <th>Admin</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td class="mono">{{ $user->id }}</td>
                            <td><strong>{{ $user->name }}</strong></td>
                            <td class="text-muted">{{ $user->email }}</td>
                            <td><span class="badge badge-active">{{ $user->plan?->name ?? 'None' }}</span></td>
                            <td>{{ $user->render_jobs_count }}</td>
                            <td>{!! $user->is_admin ? '<span class="text-accent">✓</span>' : '' !!}</td>
                            <td class="text-muted text-sm">{{ $user->created_at->format('M j, Y') }}</td>
                            <td><a href="/admin/users/{{ $user->id }}" class="btn btn-secondary btn-sm">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination">
        {{ $users->withQueryString()->links('admin.partials.pagination') }}
    </div>
@endsection