@extends('portal.layouts.app')
@section('title', 'Plans')

@section('content')
    <div class="container">
        <div style="text-align: center; margin-bottom: 32px;">
            <h2 style="font-size: 28px; margin-bottom: 8px;">Choose Your Plan</h2>
            <p class="text-dim" style="color: var(--text-dim);">Start free, upgrade when you need more power.</p>
        </div>

        @if($pendingRequest)
            <div class="alert alert-warning" style="max-width: 600px; margin: 0 auto 24px;">
                ⏳ You have a pending request for <strong>{{ $pendingRequest->plan->name }}</strong>. We'll contact you soon!
            </div>
        @endif

        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; max-width: 1000px; margin: 0 auto;">
            @foreach($plans as $plan)
                <div class="card"
                    style="position:relative; {{ $currentPlan && $currentPlan->id == $plan->id ? 'border-color: var(--accent);' : '' }}">
                    @if($currentPlan && $currentPlan->id == $plan->id)
                        <div
                            style="position:absolute; top: -1px; left: 50%; transform: translateX(-50%); background: var(--accent); color: #000; padding: 3px 16px; border-radius: 0 0 8px 8px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                            Current Plan</div>
                    @endif
                    <div class="card-body" style="padding: 28px;">
                        <div
                            style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">
                            {{ $plan->slug }}</div>
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 4px;">
                            ${{ number_format($plan->price_monthly, 0) }}<span
                                style="font-size: 16px; font-weight: 400; color: var(--text-dim);">/mo</span></div>

                        <div style="margin: 20px 0; display: grid; gap: 8px; font-size: 13px; color: var(--text-dim);">
                            <div>📹 Max <strong style="color:var(--text)">{{ $plan->max_video_duration }}s</strong> per video
                            </div>
                            <div>⏱️ <strong style="color:var(--text)">{{ $plan->max_render_minutes }} min</strong> render time
                            </div>
                            <div>📐 Up to <strong style="color:var(--text)">{{ $plan->max_resolution }}</strong> resolution
                            </div>
                            <div>⚡ <strong style="color:var(--text)">{{ $plan->rate_limit_per_minute }}</strong> requests/min
                            </div>
                            <div>📦 <strong style="color:var(--text)">{{ $plan->storage_days }} days</strong> storage</div>
                            <div>
                                {!! $plan->has_watermark ? '💧 With watermark' : '✨ <strong style="color:var(--text)">No watermark</strong>' !!}
                            </div>
                            <div>
                                {!! $plan->has_webhook ? '🔔 <strong style="color:var(--text)">Webhooks</strong>' : '🔕 No webhooks' !!}
                            </div>
                        </div>

                        @if($currentPlan && $currentPlan->id == $plan->id)
                            <button class="btn btn-secondary" style="width:100%; justify-content:center;" disabled>Current
                                Plan</button>
                        @elseif($plan->price_monthly == 0)
                            <span class="text-muted text-sm">Free tier — already included</span>
                        @elseif($pendingRequest)
                            <button class="btn btn-secondary" style="width:100%; justify-content:center;" disabled>Request
                                Pending</button>
                        @else
                            <button class="btn btn-primary" style="width:100%; justify-content:center;"
                                onclick="openRequestModal({{ $plan->id }}, '{{ $plan->name }}', '{{ $plan->price_monthly }}')">
                                Əlaqə Saxlayın
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Request Modal -->
    <div class="modal-overlay" id="requestModal">
        <div class="modal" onclick="event.stopPropagation()">
            <h3>Plan Upgrade Request</h3>
            <p class="text-muted mb-3" style="font-size:13px;">Requesting: <strong id="modalPlanName"
                    class="text-accent"></strong> ($<span id="modalPlanPrice"></span>/mo)</p>
            <form method="POST" action="/plans/request">
                @csrf
                <input type="hidden" name="plan_id" id="modalPlanId">
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" value="{{ auth()->user()->phone ?? '' }}" placeholder="+994 XX XXX XX XX"
                        required>
                </div>
                <div class="form-group">
                    <label>Message <span class="text-muted">(optional)</span></label>
                    <textarea name="message" rows="3" placeholder="Any questions or notes for us..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('requestModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Request</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        function openRequestModal(planId, name, price) {
            document.getElementById('modalPlanId').value = planId;
            document.getElementById('modalPlanName').textContent = name;
            document.getElementById('modalPlanPrice').textContent = price;
            document.getElementById('requestModal').classList.add('active');
        }
        document.getElementById('requestModal').addEventListener('click', function (e) {
            if (e.target === this) this.classList.remove('active');
        });
    </script>
@endsection