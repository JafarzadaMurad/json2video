@extends('admin.layouts.app')
@section('title', 'Render Video')
@section('breadcrumb', 'Tools → Render')

@section('styles')
    <style>
        .editor-wrap {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            min-height: 70vh;
        }

        .json-editor {
            width: 100%;
            min-height: 500px;
            background: #0a0e1a;
            border: 1px solid #1e293b;
            border-radius: 8px;
            color: #64ffda;
            font-family: 'SF Mono', Consolas, monospace;
            font-size: 13px;
            line-height: 1.6;
            padding: 20px;
            resize: vertical;
            tab-size: 2;
        }

        .json-editor:focus {
            outline: none;
            border-color: #64ffda;
        }

        .result-panel {
            position: relative;
        }

        .result-panel .status-bar {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .status-polling {
            background: rgba(96, 165, 250, 0.1);
            border: 1px solid rgba(96, 165, 250, 0.3);
            color: #60a5fa;
        }

        .status-done {
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: #4ade80;
        }

        .status-failed {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #f87171;
        }

        .status-idle {
            background: rgba(100, 116, 139, 0.1);
            border: 1px solid rgba(100, 116, 139, 0.3);
            color: #94a3b8;
        }

        .video-preview {
            width: 100%;
            border-radius: 8px;
            background: #000;
            aspect-ratio: 16/9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            margin-top: 12px;
            overflow: hidden;
        }

        .video-preview video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            align-items: center;
        }

        .templates-dropdown {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            padding: 8px 12px;
            font-size: 13px;
            font-family: inherit;
        }
    </style>
@endsection

@section('content')
    <div class="toolbar">
        <button onclick="submitRender()" class="btn btn-primary" id="submitBtn">🚀 Render Video</button>
        <button onclick="formatJson()" class="btn btn-secondary">{ } Format</button>
        <button onclick="clearEditor()" class="btn btn-secondary">✕ Clear</button>
        <select class="templates-dropdown" onchange="loadTemplate(this.value)">
            <option value="">Load template...</option>
            <option value="simple_text">Simple Text</option>
            <option value="two_scenes">Two Scenes + Transition</option>
            <option value="video_with_srt">Video + SRT</option>
        </select>
    </div>

    <div class="editor-wrap">
        <div>
            <textarea class="json-editor" id="jsonEditor" spellcheck="false" placeholder="Paste your JSON here...">{
      "resolution": "hd",
      "quality": "high",
      "scenes": [
        {
          "comment": "Scene 1",
          "duration": 5,
          "background": "#0a192f",
          "elements": [
            {
              "type": "text",
              "text": "Hello JSON2Video!",
              "font-size": 64,
              "color": "#64ffda",
              "start": 0,
              "duration": 5
            }
          ]
        }
      ]
    }</textarea>
        </div>

        <div class="result-panel">
            <div class="status-bar status-idle" id="statusBar">
                Ready — paste JSON and click "Render Video"
            </div>

            <div class="card" id="jobInfo" style="display: none;">
                <div class="card-header">
                    <h3>Job Details</h3>
                </div>
                <div class="card-body padded">
                    <table style="font-size: 13px;">
                        <tr>
                            <td class="text-muted" style="width:100px">Job ID</td>
                            <td class="mono" id="jobId">—</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td id="jobStatus">—</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Progress</td>
                            <td id="jobProgress">—</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Duration</td>
                            <td id="jobDuration">—</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Size</td>
                            <td id="jobSize">—</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="video-preview" id="videoPreview">
                <span>Video preview will appear here</span>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const API_KEY = '{{ \App\Models\ApiKey::first()?->key_prefix ?? "" }}';
        let pollInterval = null;

        const TEMPLATES = {
            simple_text: {
                resolution: "hd", quality: "high",
                scenes: [{
                    comment: "Text Scene", duration: 5, background: "#1a1a2e",
                    elements: [{
                        type: "text", text: "Hello JSON2Video!", "font-size": 72, color: "#64ffda", start: 0, duration: 5
                    }]
                }]
            },
            two_scenes: {
                resolution: "hd", quality: "high",
                scenes: [
                    {
                        comment: "Scene 1", duration: 5, background: "#0a192f",
                        elements: [{ type: "text", text: "First Scene", "font-size": 64, color: "#ffffff", start: 0, duration: 5 }]
                    },
                    {
                        comment: "Scene 2", duration: 5, background: "#1a1a2e",
                        transition: { type: "fade", duration: 1 },
                        elements: [{ type: "text", text: "Second Scene", "font-size": 64, color: "#64ffda", start: 0, duration: 5 }]
                    }
                ]
            },
            video_with_srt: {
                resolution: "custom", width: 1080, height: 1920, quality: "high",
                scenes: [{
                    comment: "Video + SRT", duration: 20,
                    elements: [
                        { type: "video", src: "http://nginx:80/media/YOUR_VIDEO.mp4", start: 0, duration: 20, mute: true },
                        { type: "subtitles", src: "http://nginx:80/media/subtitles.srt", "font-size": 32, color: "#ffcc00" }
                    ]
                }]
            }
        };

        function loadTemplate(name) {
            if (!name) return;
            document.getElementById('jsonEditor').value = JSON.stringify(TEMPLATES[name], null, 2);
        }

        function formatJson() {
            const editor = document.getElementById('jsonEditor');
            try {
                const parsed = JSON.parse(editor.value);
                editor.value = JSON.stringify(parsed, null, 2);
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
            }
        }

        function clearEditor() {
            document.getElementById('jsonEditor').value = '';
            document.getElementById('statusBar').className = 'status-bar status-idle';
            document.getElementById('statusBar').textContent = 'Ready';
            document.getElementById('jobInfo').style.display = 'none';
            document.getElementById('videoPreview').innerHTML = '<span>Video preview will appear here</span>';
            if (pollInterval) clearInterval(pollInterval);
        }

        async function submitRender() {
            const editor = document.getElementById('jsonEditor');
            let payload;

            try {
                payload = JSON.parse(editor.value);
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
                return;
            }

            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Submitting...';

            const statusBar = document.getElementById('statusBar');
            statusBar.className = 'status-bar status-polling';
            statusBar.textContent = '📤 Submitting render job...';

            try {
                const resp = await fetch('/api/v1/movies', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-API-Key': 'j2v_test_key_for_development_only_1234'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await resp.json();

                if (!resp.ok) {
                    throw new Error(data.message || JSON.stringify(data.errors || data));
                }

                const jobId = data.job_id;
                document.getElementById('jobInfo').style.display = 'block';
                document.getElementById('jobId').textContent = jobId;
                document.getElementById('jobStatus').innerHTML = '<span class="badge badge-queued">QUEUED</span>';
                document.getElementById('jobProgress').textContent = '0%';

                statusBar.textContent = '⏳ Rendering... polling for status';
                btn.textContent = '⏳ Rendering...';

                // Poll status
                pollInterval = setInterval(() => pollJobStatus(jobId), 3000);

            } catch (e) {
                statusBar.className = 'status-bar status-failed';
                statusBar.textContent = '❌ Error: ' + e.message;
                btn.disabled = false;
                btn.textContent = '🚀 Render Video';
            }
        }

        async function pollJobStatus(jobId) {
            try {
                const resp = await fetch('/api/v1/movies/' + jobId, {
                    headers: {
                        'Accept': 'application/json',
                        'X-API-Key': 'j2v_test_key_for_development_only_1234'
                    }
                });

                const data = await resp.json();
                const status = data.status;
                const statusBar = document.getElementById('statusBar');

                document.getElementById('jobStatus').innerHTML =
                    `<span class="badge badge-${status}">${status.toUpperCase()}</span>`;
                document.getElementById('jobProgress').textContent = (data.progress || 0) + '%';
                document.getElementById('jobDuration').textContent = data.duration ? data.duration + 's' : '—';
                document.getElementById('jobSize').textContent = data.size_mb ? data.size_mb + ' MB' : '—';

                if (status === 'done') {
                    clearInterval(pollInterval);
                    statusBar.className = 'status-bar status-done';
                    statusBar.textContent = '✅ Render complete! ' + (data.size_mb || '') + ' MB';

                    const btn = document.getElementById('submitBtn');
                    btn.disabled = false;
                    btn.textContent = '🚀 Render Video';

                    // Show video
                    if (data.url) {
                        document.getElementById('videoPreview').innerHTML =
                            `<video controls autoplay><source src="${data.url}" type="video/mp4"></video>`;
                    }
                } else if (status === 'failed') {
                    clearInterval(pollInterval);
                    statusBar.className = 'status-bar status-failed';
                    statusBar.textContent = '❌ Failed: ' + (data.error || 'Unknown error');

                    const btn = document.getElementById('submitBtn');
                    btn.disabled = false;
                    btn.textContent = '🚀 Render Video';
                } else {
                    statusBar.textContent = `⏳ ${status}... ${data.progress || 0}%`;
                }
            } catch (e) {
                console.error('Poll error:', e);
            }
        }

        // Tab support in editor
        document.getElementById('jsonEditor').addEventListener('keydown', function (e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                const s = this.selectionStart;
                this.value = this.value.substring(0, s) + '  ' + this.value.substring(this.selectionEnd);
                this.selectionStart = this.selectionEnd = s + 2;
            }
        });
    </script>
@endsection