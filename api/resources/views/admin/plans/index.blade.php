@extends('admin.layouts.app')
@section('title', 'Plans')
@section('breadcrumb', 'Management → Plans')

@section('content')
    <div class="flex justify-between items-center mb-4">
        <div></div>
        <button onclick="document.getElementById('createModal').classList.add('active')" class="btn btn-primary">+ New
            Plan</button>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
        @foreach($plans as $plan)
            <div class="stat-card" style="cursor: pointer;"
                onclick="document.getElementById('editModal{{ $plan->id }}').classList.add('active')">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="label">{{ $plan->slug }}</div>
                        <div class="value">${{ $plan->price_monthly }}</div>
                        <div class="sub">per month</div>
                    </div>
                    <div style="text-align: right;">
                        <span
                            class="badge badge-{{ $plan->is_active ? 'active' : 'inactive' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                        <div class="text-sm text-muted mt-4">{{ $plan->users_count }} users</div>
                    </div>
                </div>
                <div
                    style="margin-top: 16px; font-size: 12px; color: var(--text-dim); display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                    <span>📹 Max {{ $plan->max_video_duration ?? '∞' }}s</span>
                    <span>📐 {{ $plan->max_resolution }}</span>
                    <span>⚡ {{ $plan->rate_limit_per_minute }}/min</span>
                    <span>📦 {{ $plan->storage_days }} days</span>
                    <span>{!! $plan->has_watermark ? '💧 Watermark' : '✨ No watermark' !!}</span>
                    <span>{!! $plan->has_webhook ? '🔔 Webhooks' : '' !!}</span>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal-overlay" id="editModal{{ $plan->id }}">
                <div class="modal" onclick="event.stopPropagation()">
                    <h3>Edit Plan: {{ $plan->name }}</h3>
                    <form method="POST" action="/admin/plans/{{ $plan->id }}">
                        @csrf @method('PUT')
                        <div class="form-row">
                            <div class="form-group"><label>Name</label><input type="text" name="name" value="{{ $plan->name }}"
                                    required></div>
                            <div class="form-group"><label>Price ($/mo)</label><input type="number" name="price_monthly"
                                    value="{{ $plan->price_monthly }}" step="0.01" required></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Max Duration (s)</label><input type="number"
                                    name="max_video_duration" value="{{ $plan->max_video_duration }}"></div>
                            <div class="form-group"><label>Max Resolution</label>
                                <select name="max_resolution">
                                    @foreach(['sd', 'hd', 'full-hd', '2k', '4k'] as $r)<option value="{{ $r }}" {{ $plan->max_resolution == $r ? 'selected' : '' }}>{{ $r }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Rate Limit (/min)</label><input type="number"
                                    name="rate_limit_per_minute" value="{{ $plan->rate_limit_per_minute }}" required></div>
                            <div class="form-group"><label>Storage (days)</label><input type="number" name="storage_days"
                                    value="{{ $plan->storage_days }}" required></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Max Render Minutes</label><input type="number"
                                    name="max_render_minutes" value="{{ $plan->max_render_minutes }}"></div>
                            <div class="form-group"></div>
                        </div>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
                            <div class="form-check"><input type="checkbox" name="has_watermark" value="1" {{ $plan->has_watermark ? 'checked' : '' }}><label>Watermark</label></div>
                            <div class="form-check"><input type="checkbox" name="has_priority_queue" value="1" {{ $plan->has_priority_queue ? 'checked' : '' }}><label>Priority Queue</label></div>
                            <div class="form-check"><input type="checkbox" name="has_webhook" value="1" {{ $plan->has_webhook ? 'checked' : '' }}><label>Webhooks</label></div>
                            <div class="form-check"><input type="checkbox" name="has_templates" value="1" {{ $plan->has_templates ? 'checked' : '' }}><label>Templates</label></div>
                            <div class="form-check"><input type="checkbox" name="is_active" value="1" {{ $plan->is_active ? 'checked' : '' }}><label>Active</label></div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary"
                                onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                    @if($plan->users_count == 0)
                        <form method="POST" action="/admin/plans/{{ $plan->id }}" style="margin-top: 12px;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this plan?')">Delete
                                Plan</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Create Modal -->
    <div class="modal-overlay" id="createModal">
        <div class="modal" onclick="event.stopPropagation()">
            <h3>Create New Plan</h3>
            <form method="POST" action="/admin/plans">
                @csrf
                <div class="form-row">
                    <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Slug</label><input type="text" name="slug" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Price ($/mo)</label><input type="number" name="price_monthly" step="0.01"
                            required></div>
                    <div class="form-group"><label>Max Resolution</label>
                        <select name="max_resolution">
                            @foreach(['sd', 'hd', 'full-hd', '2k', '4k'] as $r)<option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Rate Limit (/min)</label><input type="number"
                            name="rate_limit_per_minute" value="30" required></div>
                    <div class="form-group"><label>Storage (days)</label><input type="number" name="storage_days" value="7"
                            required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Max Duration (s)</label><input type="number" name="max_video_duration">
                    </div>
                    <div class="form-group"><label>Max Render Minutes</label><input type="number" name="max_render_minutes">
                    </div>
                </div>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
                    <div class="form-check"><input type="checkbox" name="has_watermark" value="1"><label>Watermark</label>
                    </div>
                    <div class="form-check"><input type="checkbox" name="has_priority_queue" value="1"><label>Priority
                            Queue</label></div>
                    <div class="form-check"><input type="checkbox" name="has_webhook" value="1"><label>Webhooks</label>
                    </div>
                    <div class="form-check"><input type="checkbox" name="has_templates" value="1"><label>Templates</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary"
                        onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Plan</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('scripts')
    <script>
        document.querySelectorAll('.modal-overlay').forEach(el => {
            el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
        });
    </script>
@endsection