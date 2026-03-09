@extends('portal.layouts.app')
@section('title', 'JSON2Video — Video Generation API')

@section('styles')
    <style>
        .hero {
            text-align: center;
            padding: 80px 32px 60px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 50% 0%, rgba(100, 255, 218, 0.08) 0%, transparent 60%);
            pointer-events: none;
        }

        .hero h1 {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 16px;
        }

        .hero h1 span {
            background: linear-gradient(135deg, #64ffda, #4facfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 18px;
            color: var(--text-dim);
            max-width: 600px;
            margin: 0 auto 32px;
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .hero-code {
            max-width: 700px;
            margin: 40px auto 0;
            text-align: left;
        }

        .features {
            padding: 60px 32px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .features h2 {
            text-align: center;
            font-size: 32px;
            margin-bottom: 40px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }

        .feature-card .icon {
            font-size: 28px;
            margin-bottom: 12px;
        }

        .feature-card h3 {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .feature-card p {
            font-size: 13px;
            color: var(--text-dim);
            line-height: 1.6;
        }

        .docs {
            padding: 60px 32px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .docs h2 {
            font-size: 32px;
            margin-bottom: 12px;
            text-align: center;
        }

        .docs .subtitle {
            text-align: center;
            color: var(--text-dim);
            margin-bottom: 40px;
            font-size: 16px;
        }

        .endpoint {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .endpoint-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .endpoint-header:hover {
            background: var(--bg-card-hover);
        }

        .endpoint-method {
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            font-family: monospace;
        }

        .method-post {
            background: rgba(74, 222, 128, 0.15);
            color: #4ade80;
        }

        .method-get {
            background: rgba(96, 165, 250, 0.15);
            color: #60a5fa;
        }

        .method-delete {
            background: rgba(248, 113, 113, 0.15);
            color: #f87171;
        }

        .endpoint-path {
            font-family: monospace;
            font-size: 14px;
            color: var(--text);
        }

        .endpoint-desc {
            font-size: 13px;
            color: var(--text-dim);
            margin-left: auto;
        }

        .endpoint-body {
            display: none;
            padding: 20px;
            border-top: 1px solid var(--border);
        }

        .endpoint-body.open {
            display: block;
        }

        pre.code {
            background: #0d1117;
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.6;
            font-family: 'SF Mono', Consolas, monospace;
            color: #e6edf3;
            margin: 12px 0;
        }

        .code .key {
            color: #7ee787;
        }

        .code .str {
            color: #a5d6ff;
        }

        .code .num {
            color: #f2cc60;
        }

        .code .comment {
            color: #8b949e;
        }

        .pricing {
            padding: 60px 32px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .pricing h2 {
            text-align: center;
            font-size: 32px;
            margin-bottom: 40px;
        }

        .footer {
            padding: 32px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 40px;
        }
    </style>
@endsection

@section('content')
    <!-- Hero -->
    <section class="hero">
        <h1>Generate Videos with <span>JSON</span></h1>
        <p>Powerful REST API for programmatic video creation. Compose scenes, add text, images, videos, subtitles,
            transitions, and animations — all from a simple JSON payload.</p>
        <div class="hero-actions">
            <a href="/register" class="btn btn-primary" style="padding: 12px 28px; font-size: 15px;">Start Free →</a>
            <a href="#docs" class="btn btn-secondary" style="padding: 12px 28px; font-size: 15px;">📖 Documentation</a>
        </div>
        <div class="hero-code">
            <pre class="code"><span class="comment">// Create a video with one API call</span>
        curl -X POST /api/v1/movies \
          -H "<span class="key">X-API-Key</span>: <span class="str">your_api_key</span>" \
          -H "<span class="key">Content-Type</span>: <span class="str">application/json</span>" \
          -d '{
            "<span class="key">resolution</span>": "<span class="str">hd</span>",
            "<span class="key">scenes</span>": [{
              "<span class="key">duration</span>": <span class="num">5</span>,
              "<span class="key">elements</span>": [{
                "<span class="key">type</span>": "<span class="str">text</span>",
                "<span class="key">text</span>": "<span class="str">Hello World!</span>"
              }]
            }]
          }'</pre>
        </div>
    </section>

    <!-- Features -->
    <section class="features">
        <h2>Why JSON2Video?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="icon">🎬</div>
                <h3>Multiple Elements</h3>
                <p>Text, images, videos, audio, subtitles — compose complex scenes with multiple overlapping elements.</p>
            </div>
            <div class="feature-card">
                <div class="icon">✨</div>
                <h3>Transitions & Animations</h3>
                <p>Fade, slide, zoom, dissolve, wipe transitions. 8 element animations with 4 easing functions.</p>
            </div>
            <div class="feature-card">
                <div class="icon">📐</div>
                <h3>Custom Resolutions</h3>
                <p>1080×1920 (Reels), 1920×1080 (YouTube), or any custom resolution up to 4K.</p>
            </div>
            <div class="feature-card">
                <div class="icon">📝</div>
                <h3>SRT Subtitles</h3>
                <p>Full SRT parser with timed subtitles. Each line appears at the correct timestamp.</p>
            </div>
            <div class="feature-card">
                <div class="icon">🔔</div>
                <h3>Webhooks</h3>
                <p>Get notified when your video is ready. No polling needed — we'll call your endpoint.</p>
            </div>
            <div class="feature-card">
                <div class="icon">📋</div>
                <h3>Templates</h3>
                <p>Save and reuse video templates with variable substitution. Render thousands of variations.</p>
            </div>
        </div>
    </section>

    <!-- API Documentation -->
    <section class="docs" id="docs">
        <h2>API Documentation</h2>
        <p class="subtitle">Everything you need to start creating videos programmatically.</p>

        <!-- Auth -->
        <h3 style="font-size: 18px; margin: 32px 0 16px; color: var(--accent);">🔐 Authentication</h3>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-body">
                <p style="font-size: 14px; color: var(--text-dim); line-height: 1.7;">
                    All API requests require authentication via the <code class="mono"
                        style="color: var(--accent);">X-API-Key</code> header.
                    You receive an API key when you <a href="/register">create an account</a>.
                </p>
                <pre class="code">curl -H "<span class="key">X-API-Key</span>: <span class="str">j2v_abc123...</span>" \
             -H "<span class="key">Accept</span>: <span class="str">application/json</span>" \
             {{ config('app.url') }}/api/v1/movies</pre>
            </div>
        </div>

        <!-- Endpoints -->
        <h3 style="font-size: 18px; margin: 32px 0 16px; color: var(--accent);">📡 Endpoints</h3>

        <!-- POST /movies -->
        <div class="endpoint">
            <div class="endpoint-header" onclick="this.nextElementSibling.classList.toggle('open')">
                <span class="endpoint-method method-post">POST</span>
                <span class="endpoint-path">/api/v1/movies</span>
                <span class="endpoint-desc">Create a new video</span>
            </div>
            <div class="endpoint-body">
                <p style="font-size: 13px; color: var(--text-dim); margin-bottom: 12px;">Submit a JSON payload to queue a
                    video rendering job.</p>
                <strong style="font-size: 12px; color: var(--text-muted);">REQUEST BODY:</strong>
                <pre class="code">{
          "<span class="key">resolution</span>": "<span class="str">hd</span>",           <span class="comment">// sd, hd, full-hd, 2k, 4k, or custom</span>
          "<span class="key">width</span>": <span class="num">1080</span>,               <span class="comment">// only with resolution: custom</span>
          "<span class="key">height</span>": <span class="num">1920</span>,              <span class="comment">// only with resolution: custom</span>
          "<span class="key">quality</span>": "<span class="str">high</span>",            <span class="comment">// low, medium, high, ultra</span>
          "<span class="key">fps</span>": <span class="num">30</span>,                   <span class="comment">// 24, 25, 30, 60</span>
          "<span class="key">scenes</span>": [
            {
              "<span class="key">comment</span>": "<span class="str">My scene</span>",
              "<span class="key">duration</span>": <span class="num">5</span>,
              "<span class="key">background</span>": "<span class="str">#000000</span>",
              "<span class="key">transition</span>": { "<span class="key">type</span>": "<span class="str">fade</span>", "<span class="key">duration</span>": <span class="num">1</span> },
              "<span class="key">elements</span>": [ ... ]
            }
          ]
        }</pre>
                <strong style="font-size: 12px; color: var(--text-muted); display: block; margin-top: 16px;">RESPONSE
                    (202):</strong>
                <pre class="code">{
          "<span class="key">job_id</span>": "<span class="str">019cc532-251e-...</span>",
          "<span class="key">status</span>": "<span class="str">queued</span>",
          "<span class="key">message</span>": "<span class="str">Render job created</span>"
        }</pre>
            </div>
        </div>

        <!-- GET /movies/:id -->
        <div class="endpoint">
            <div class="endpoint-header" onclick="this.nextElementSibling.classList.toggle('open')">
                <span class="endpoint-method method-get">GET</span>
                <span class="endpoint-path">/api/v1/movies/{id}</span>
                <span class="endpoint-desc">Get job status</span>
            </div>
            <div class="endpoint-body">
                <pre class="code">{
          "<span class="key">job_id</span>": "<span class="str">019cc532-...</span>",
          "<span class="key">status</span>": "<span class="str">done</span>",
          "<span class="key">progress</span>": <span class="num">100</span>,
          "<span class="key">url</span>": "<span class="str">{{ config('app.url') }}/storage/videos/019cc532.mp4</span>",
          "<span class="key">duration</span>": <span class="num">5.0</span>,
          "<span class="key">size_mb</span>": <span class="num">2.4</span>
        }</pre>
            </div>
        </div>

        <!-- Elements reference -->
        <h3 style="font-size: 18px; margin: 32px 0 16px; color: var(--accent);">🧩 Element Types</h3>

        <div class="endpoint">
            <div class="endpoint-header" onclick="this.nextElementSibling.classList.toggle('open')">
                <span class="endpoint-method" style="background: rgba(167,139,250,0.15); color: #a78bfa;">TEXT</span>
                <span class="endpoint-path">type: "text"</span>
                <span class="endpoint-desc">Text overlay</span>
            </div>
            <div class="endpoint-body">
                <pre class="code">{
          "<span class="key">type</span>": "<span class="str">text</span>",
          "<span class="key">text</span>": "<span class="str">Hello World!</span>",
          "<span class="key">font-size</span>": <span class="num">48</span>,
          "<span class="key">color</span>": "<span class="str">#ffffff</span>",
          "<span class="key">background</span>": "<span class="str">#000000</span>",          <span class="comment">// optional bg color</span>
          "<span class="key">x</span>": <span class="num">100</span>, "<span class="key">y</span>": <span class="num">200</span>,                <span class="comment">// position (optional)</span>
          "<span class="key">start</span>": <span class="num">0</span>, "<span class="key">duration</span>": <span class="num">5</span>,          <span class="comment">// timing (optional)</span>
          "<span class="key">animation</span>": { "<span class="key">type</span>": "<span class="str">fade-in</span>" } <span class="comment">// optional</span>
        }</pre>
            </div>
        </div>

        <div class="endpoint">
            <div class="endpoint-header" onclick="this.nextElementSibling.classList.toggle('open')">
                <span class="endpoint-method" style="background: rgba(167,139,250,0.15); color: #a78bfa;">VIDEO</span>
                <span class="endpoint-path">type: "video"</span>
                <span class="endpoint-desc">Embed video clip</span>
            </div>
            <div class="endpoint-body">
                <pre class="code">{
          "<span class="key">type</span>": "<span class="str">video</span>",
          "<span class="key">src</span>": "<span class="str">https://example.com/clip.mp4</span>",
          "<span class="key">mute</span>": <span class="num">true</span>,                    <span class="comment">// mute audio</span>
          "<span class="key">volume</span>": <span class="num">0.5</span>,                   <span class="comment">// volume (0-1)</span>
          "<span class="key">start</span>": <span class="num">0</span>, "<span class="key">duration</span>": <span class="num">10</span>,
          "<span class="key">trim-start</span>": <span class="num">5</span>,                 <span class="comment">// trim from second 5</span>
          "<span class="key">trim-end</span>": <span class="num">15</span>                   <span class="comment">// to second 15</span>
        }</pre>
                <p style="font-size: 12px; color: var(--text-dim); margin-top: 8px;">Tip: Without explicit width/height,
                    video auto-fits canvas keeping aspect ratio and centers itself.</p>
            </div>
        </div>

        <div class="endpoint">
            <div class="endpoint-header" onclick="this.nextElementSibling.classList.toggle('open')">
                <span class="endpoint-method" style="background: rgba(167,139,250,0.15); color: #a78bfa;">IMAGE</span>
                <span class="endpoint-path">type: "image"</span>
                <span class="endpoint-desc">Image overlay</span>
            </div>
            <div class="endpoint-body">
                <pre class="code">{
          "<span class="key">type</span>": "<span class="str">image</span>",
          "<span class="key">src</span>": "<span class="str">https://example.com/logo.png</span>",
          "<span class="key">width</span>": <span class="num">200</span>, "<span class="key">height</span>": <span class="num">200</span>,
          "<span class="key">x</span>": <span class="num">50</span>, "<span class="key">y</span>": <span class="num">50</span>,
          "<span class="key">start</span>": <span class="num">0</span>, "<span class="key">duration</span>": <span class="num">5</span>
        }</pre>
            </div>
        </div>

        <div class="endpoint">
            <div class="endpoint-header" onclick="this.nextElementSibling.classList.toggle('open')">
                <span class="endpoint-method" style="background: rgba(167,139,250,0.15); color: #a78bfa;">SUBTITLES</span>
                <span class="endpoint-path">type: "subtitles"</span>
                <span class="endpoint-desc">SRT subtitles or inline text</span>
            </div>
            <div class="endpoint-body">
                <pre class="code"><span class="comment">// From SRT file (each line appears at its timestamp):</span>
        {
          "<span class="key">type</span>": "<span class="str">subtitles</span>",
          "<span class="key">src</span>": "<span class="str">https://example.com/subs.srt</span>",
          "<span class="key">font-size</span>": <span class="num">32</span>,
          "<span class="key">color</span>": "<span class="str">#ffcc00</span>"
        }

        <span class="comment">// Or inline text:</span>
        {
          "<span class="key">type</span>": "<span class="str">subtitles</span>",
          "<span class="key">text</span>": "<span class="str">My subtitle text</span>",
          "<span class="key">start</span>": <span class="num">2</span>, "<span class="key">duration</span>": <span class="num">3</span>
        }</pre>
            </div>
        </div>

        <!-- Transitions -->
        <h3 style="font-size: 18px; margin: 32px 0 16px; color: var(--accent);">🔀 Transitions</h3>
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-body">
                <p style="font-size: 13px; color: var(--text-dim); margin-bottom: 12px;">Add to a scene to transition from
                    the previous scene:</p>
                <pre class="code">"<span class="key">transition</span>": {
          "<span class="key">type</span>": "<span class="str">fade</span>",     <span class="comment">// fade, slide-left, slide-right, slide-up, slide-down, zoom-in, zoom-out, dissolve, wipe</span>
          "<span class="key">duration</span>": <span class="num">1</span>       <span class="comment">// seconds</span>
        }</pre>
            </div>
        </div>

        <!-- Animations -->
        <h3 style="font-size: 18px; margin: 32px 0 16px; color: var(--accent);">💫 Animations</h3>
        <div class="card">
            <div class="card-body">
                <p style="font-size: 13px; color: var(--text-dim); margin-bottom: 12px;">Add to any element for
                    entrance/exit animations:</p>
                <pre class="code">"<span class="key">animation</span>": {
          "<span class="key">type</span>": "<span class="str">fade-in</span>",   <span class="comment">// fade-in, fade-out, slide-in-left, slide-in-right, slide-in-top, slide-in-bottom, zoom-in, bounce</span>
          "<span class="key">duration</span>": <span class="num">0.5</span>,     <span class="comment">// seconds</span>
          "<span class="key">easing</span>": "<span class="str">ease-out</span>"  <span class="comment">// linear, ease-in, ease-out, ease-in-out</span>
        }</pre>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section class="pricing" id="pricing">
        <h2>Pricing</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            @php $plans = \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get(); @endphp
            @foreach($plans as $plan)
                <div class="card">
                    <div class="card-body" style="padding: 28px; text-align: center;">
                        <div
                            style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">
                            {{ $plan->name }}
                        </div>
                        <div style="font-size: 40px; font-weight: 700;">${{ number_format($plan->price_monthly, 0) }}<span
                                style="font-size: 16px; font-weight: 400; color: var(--text-dim);">/mo</span></div>
                        <div
                            style="margin: 20px 0; font-size: 13px; color: var(--text-dim); text-align: left; display: grid; gap: 6px;">
                            <div>✓ {{ $plan->max_render_minutes }} min render</div>
                            <div>✓ {{ $plan->max_video_duration }}s max duration</div>
                            <div>✓ {{ $plan->max_resolution }} resolution</div>
                            <div>✓ {{ $plan->storage_days }} days storage</div>
                        </div>
                        @if($plan->price_monthly == 0)
                            <a href="/register" class="btn btn-primary" style="width:100%; justify-content:center;">Start Free</a>
                        @else
                            <a href="/register" class="btn btn-secondary" style="width:100%; justify-content:center;">Get
                                Started</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <footer class="footer">
        <p>© {{ date('Y') }} JSON2Video — Video Generation API</p>
    </footer>
@endsection