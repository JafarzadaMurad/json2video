@extends('admin.layouts.app')
@section('title', 'Plan Requests')
@section('breadcrumb', 'Management → Plan Requests')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>{{ $requests->total() }} Plan Requests</h3>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Requested Plan</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <td><strong>{{ $req->user->name }}</strong></td>
                            <td class="text-muted">{{ $req->user->email }}</td>
                            <td>{{ $req->phone ?? '—' }}</td>
                            <td><span class="badge badge-active">{{ $req->plan->name }}</span>
                                (${{ $req->plan->price_monthly }}/mo)</td>
                            <td class="text-muted text-sm" style="max-width:200px;">{{ Str::limit($req->message ?? '—', 50) }}
                            </td>
                            <td>
                                @if($req->status === 'pending')
                                    <span class="badge badge-queued">PENDING</span>
                                @elseif($req->status === 'approved')
                                    <span class="badge badge-done">APPROVED</span>
                                @else
                                    <span class="badge badge-failed">REJECTED</span>
                                @endif
                            </td>
                            <td class="text-muted text-sm">{{ $req->created_at->diffForHumans() }}</td>
                            <td>
                                @if($req->status === 'pending')
                                    <div style="display:flex; gap:4px;">
                                        <form method="POST" action="/admin/plan-requests/{{ $req->id }}/approve">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm"
                                                onclick="return confirm('Approve and change user plan?')">✓ Approve</button>
                                        </form>
                                        <form method="POST" action="/admin/plan-requests/{{ $req->id }}/reject">
                                            @csrf
                                            <button type="submit" class="btn btn-danger btn-sm">✕</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-muted" style="text-align:center; padding:40px;">No plan requests yet
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection