# JSON2Video Clone — Agent Prompt (EN + AZ)

---

## 🇬🇧 ENGLISH PROMPT

You are a senior full-stack engineer. We are building a **video generation SaaS platform** — similar to json2video.com — where users send a JSON payload and receive a rendered MP4 video. The service is priced at **$100/month for unlimited usage**.

---

### 🏗️ Architecture Overview

The system consists of two main components:

**1. Laravel (PHP) — API Layer**
- Handles user authentication via API keys (`X-API-Key` header)
- Validates incoming JSON payloads
- Dispatches render jobs to a Redis queue (does NOT process videos itself)
- Returns job status and download URLs
- Manages subscriptions and billing

**2. Python Microservice — Render Engine**
- Listens to the Redis queue for new render jobs
- Processes the JSON payload using FFmpeg + MoviePy + Pillow
- Handles all element types: image, video, text, audio, voice (TTS), subtitles
- Uploads the final MP4 to S3/Cloudflare R2
- Updates job status in the database

> ⚠️ IMPORTANT: Python is NOT called from within PHP using `shell_exec()`. They run as **separate services** communicating via **Redis queues**. This is mandatory for scalability.

---

### 📡 API Endpoints

#### `POST /api/v1/movies`
Start a new video render job.

**Headers:**
```
X-API-Key: {user_api_key}
Content-Type: application/json
```

**Request Body:**
```json
{
  "resolution": "full-hd",
  "quality": "high",
  "scenes": [
    {
      "comment": "Intro scene",
      "duration": 5,
      "elements": [
        {
          "type": "image",
          "src": "https://example.com/background.jpg",
          "width": 1920,
          "height": 1080,
          "x": 0,
          "y": 0
        },
        {
          "type": "text",
          "text": "Hello World",
          "x": 100,
          "y": 200,
          "font-size": 48,
          "color": "#ffffff",
          "start": 0,
          "duration": 5
        },
        {
          "type": "voice",
          "text": "Welcome to our platform",
          "voice": "en-US",
          "start": 0
        },
        {
          "type": "audio",
          "src": "https://example.com/bg-music.mp3",
          "volume": 0.3,
          "start": 0
        }
      ]
    },
    {
      "comment": "Second scene",
      "duration": 4,
      "elements": [
        {
          "type": "video",
          "src": "https://example.com/clip.mp4",
          "width": 1920,
          "height": 1080
        },
        {
          "type": "subtitles",
          "text": "This is a subtitle",
          "font-size": 32,
          "color": "#ffffff",
          "y": 900
        }
      ]
    }
  ]
}
```

**Response (202 Accepted):**
```json
{
  "job_id": "abc123xyz",
  "status": "queued",
  "created_at": "2026-03-04T10:00:00Z"
}
```

---

#### `GET /api/v1/movies/{job_id}`
Check the render status of a job.

**Response — Processing:**
```json
{
  "job_id": "abc123xyz",
  "status": "processing",
  "progress": 45
}
```

**Response — Done:**
```json
{
  "job_id": "abc123xyz",
  "status": "done",
  "url": "https://cdn.yourdomain.com/videos/abc123xyz.mp4",
  "duration": 9,
  "size_mb": 18.4,
  "created_at": "2026-03-04T10:00:00Z",
  "completed_at": "2026-03-04T10:01:12Z"
}
```

**Response — Failed:**
```json
{
  "job_id": "abc123xyz",
  "status": "failed",
  "error": "Invalid video source URL in scene 2"
}
```

---

#### `GET /api/v1/movies`
List all render jobs for the authenticated user.

**Query params:** `?page=1&limit=20&status=done`

---

#### `DELETE /api/v1/movies/{job_id}`
Delete a video and its associated files.

---

### 🧩 Supported Element Types

| Type | Description | Key Fields |
|------|-------------|------------|
| `image` | Background or overlay image | `src`, `x`, `y`, `width`, `height`, `start`, `duration` |
| `video` | Embed a video clip | `src`, `x`, `y`, `width`, `height`, `volume` |
| `text` | Text overlay | `text`, `x`, `y`, `font-size`, `color`, `font-family` |
| `audio` | Background music/sound | `src`, `volume`, `start`, `duration` |
| `voice` | Text-to-Speech narration | `text`, `voice` (locale), `start` |
| `subtitles` | Subtitle overlay | `text`, `y`, `font-size`, `color` | or src||ass 'srt||ass file url'

---

### 🔄 Job Lifecycle

```
POST /movies → queued → processing → done (or failed)
```

All video rendering is **asynchronous**. The client must poll `GET /movies/{job_id}` or use a webhook (optional feature) to know when the video is ready.

---

### 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| API | Laravel 11 (PHP 8.3) |
| Queue | Redis + Laravel Queues |
| Render Engine | Python 3.11 + FFmpeg + MoviePy |
| TTS | Google TTS / ElevenLabs API |
| Storage | AWS S3 or Cloudflare R2 |
| Database | MySQL or PostgreSQL |
| Auth | API Key (hashed in DB) |

---

### ✅ Your Tasks

1. Set up Laravel API with all 4 endpoints
2. Implement API key authentication middleware
3. Create the Job model and queue dispatch logic
4. Build the Python render worker that:
   - Reads jobs from Redis queue
   - Processes each element type with FFmpeg/MoviePy
   - Uploads result to S3
   - Updates job status in DB
5. Write Docker Compose config to run Laravel + Python Worker + Redis together

---
---

## 🇦🇿 AZƏRBAYCAN DİLİNDƏ PROMPT

Sən senior full-stack mühəndississən. Biz **video generasiya SaaS platforması** qururuq — json2video.com-a bənzər — istifadəçilər JSON payload göndərir və hazır MP4 video alırlar. Servisin qiyməti **aylıq $100, limitsiz istifadə**.

---

### 🏗️ Arxitektura

Sistem iki əsas hissədən ibarətdir:

**1. Laravel (PHP) — API Layeri**
- API key ilə istifadəçi autentifikasiyası (`X-API-Key` header)
- Gələn JSON payload-ları yoxlayır (validation)
- Render işlərini Redis queue-ya göndərir (videonu özü işləmir)
- Job statusu və yükləmə URL-lərini qaytarır
- Abunəlik və billing idarəsi

**2. Python Microservice — Render Mühərriki**
- Redis queue-dan yeni render işlərini oxuyur
- JSON payload-ı FFmpeg + MoviePy + Pillow ilə işləyir
- Bütün element tipləri: image, video, text, audio, voice (TTS), subtitles
- Hazır MP4-ü S3/Cloudflare R2-ə yükləyir
- Job statusunu bazada yeniləyir

> ⚠️ ÖNƏMLİ: Python PHP-nin içindən `shell_exec()` ilə çağırılmır. Onlar **ayrı servisler** kimi işləyir və **Redis queue** vasitəsilə əlaqə saxlayırlar. Bu scalability üçün məcburidir.

---

### 📡 API Endpoint-lər

#### `POST /api/v1/movies`
Yeni video render işi başlat.

**Header-lər:**
```
X-API-Key: {istifadəçi_api_açarı}
Content-Type: application/json
```

**Request Body:**
```json
{
  "resolution": "full-hd",
  "quality": "high",
  "scenes": [
    {
      "comment": "Giriş səhnəsi",
      "duration": 5,
      "elements": [
        {
          "type": "image",
          "src": "https://example.com/arxa-fon.jpg",
          "width": 1920,
          "height": 1080,
          "x": 0,
          "y": 0
        },
        {
          "type": "text",
          "text": "Salam Dünya!",
          "x": 100,
          "y": 200,
          "font-size": 48,
          "color": "#ffffff",
          "start": 0,
          "duration": 5
        },
        {
          "type": "voice",
          "text": "Platformamıza xoş gəlmisiniz",
          "voice": "az-AZ",
          "start": 0
        },
        {
          "type": "audio",
          "src": "https://example.com/musiqi.mp3",
          "volume": 0.3,
          "start": 0
        }
      ]
    },
    {
      "comment": "İkinci səhnə",
      "duration": 4,
      "elements": [
        {
          "type": "video",
          "src": "https://example.com/klip.mp4",
          "width": 1920,
          "height": 1080
        },
        {
          "type": "subtitles",
          "text": "Bu bir altyazıdır",
          "font-size": 32,
          "color": "#ffffff",
          "y": 900
        }
      ]
    }
  ]
}
```

**Response (202 Accepted):**
```json
{
  "job_id": "abc123xyz",
  "status": "queued",
  "created_at": "2026-03-04T10:00:00Z"
}
```

---

#### `GET /api/v1/movies/{job_id}`
Render işinin statusunu yoxla.

**Response — İşlənir:**
```json
{
  "job_id": "abc123xyz",
  "status": "processing",
  "progress": 45
}
```

**Response — Hazır:**
```json
{
  "job_id": "abc123xyz",
  "status": "done",
  "url": "https://cdn.səninsayt.com/videos/abc123xyz.mp4",
  "duration": 9,
  "size_mb": 18.4,
  "created_at": "2026-03-04T10:00:00Z",
  "completed_at": "2026-03-04T10:01:12Z"
}
```

**Response — Xəta:**
```json
{
  "job_id": "abc123xyz",
  "status": "failed",
  "error": "2-ci səhnədə etibarsız video URL"
}
```

---

#### `GET /api/v1/movies`
Autentifikasiya edilmiş istifadəçinin bütün render işlərini listə al.

**Sorgu parametrləri:** `?page=1&limit=20&status=done`

---

#### `DELETE /api/v1/movies/{job_id}`
Videonu və əlaqəli faylları sil.

---

### 🧩 Dəstəklənən Element Tipləri

| Tip | Açıqlama | Əsas Sahələr |
|-----|----------|--------------|
| `image` | Arxa fon və ya üzərlik şəkil | `src`, `x`, `y`, `width`, `height`, `start`, `duration` |
| `video` | Video klip əlavə et | `src`, `x`, `y`, `width`, `height`, `volume` |
| `text` | Mətn overlay | `text`, `x`, `y`, `font-size`, `color`, `font-family` |
| `audio` | Arxa fon musiqisi/səs | `src`, `volume`, `start`, `duration` |
| `voice` | TTS səsləndirmə | `text`, `voice` (lokal), `start` |
| `subtitles` | Altyazı overlay | `text`, `y`, `font-size`, `color` | or src||ass 'srt||ass file url'


---

### 🔄 İş Dövrü

```
POST /movies → queued (növbədə) → processing (işlənir) → done (hazır) və ya failed (xəta)
```

Bütün video render əməliyyatları **asinxrondur**. Müştəri ya `GET /movies/{job_id}` sorğusunu təkrarlayır ya da webhook (əlavə xüsusiyyət) istifadə edərək videonun hazır olduğunu öyrənir.

---

### 🛠️ Texnoloji Stack

| Qat | Texnologiya |
|-----|-------------|
| API | Laravel 11 (PHP 8.3) |
| Növbə | Redis + Laravel Queues |
| Render Mühərriki | Python 3.11 + FFmpeg + MoviePy |
| TTS | Google TTS / ElevenLabs API |
| Yaddaş | AWS S3 və ya Cloudflare R2 |
| Verilənlər Bazası | MySQL və ya PostgreSQL |
| Auth | API Key (bazada hash-lənmiş) |

---

### ✅ Tapşırıqların Siyahısı

1. Laravel API-ni bütün 4 endpoint ilə qur
2. API key autentifikasiya middleware-i yaz
3. Job modeli və queue dispatch məntiqi yarat
4. Python render worker-i qur:
   - Redis queue-dan işləri oxu
   - Hər element tipini FFmpeg/MoviePy ilə işlə
   - Nəticəni S3-ə yüklə
   - Job statusunu bazada yenilə
5. Laravel + Python Worker + Redis-i birlikdə işlətmək üçün Docker Compose konfiqurasiyası yaz