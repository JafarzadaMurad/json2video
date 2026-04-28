"""
JSON2Video — Audio/Video Transcription Service

Transcribes audio/video files to SRT subtitle format using faster_whisper.
Supports direct audio files and video files (auto-extracts audio via FFmpeg).
"""
import logging
import math
import os
import subprocess
import tempfile

logger = logging.getLogger('transcriber')

# Whisper model (loaded lazily, cached after first use)
_whisper_model = None
_model_size = os.environ.get('WHISPER_MODEL', 'large-v3-turbo')


def _get_model():
    """Get or initialize the Whisper model (singleton)."""
    global _whisper_model
    if _whisper_model is None:
        from faster_whisper import WhisperModel
        device = os.environ.get('WHISPER_DEVICE', 'cpu')
        compute_type = 'float16' if device == 'cuda' else 'int8'
        logger.info(f'Loading Whisper model: {_model_size} (device={device}, compute={compute_type})')
        _whisper_model = WhisperModel(_model_size, device=device, compute_type=compute_type)
        logger.info('Whisper model loaded successfully ✅')
    return _whisper_model


def _format_srt_time(seconds: float) -> str:
    """Format seconds to SRT timestamp: HH:MM:SS,mmm"""
    hours = math.floor(seconds / 3600)
    seconds %= 3600
    minutes = math.floor(seconds / 60)
    seconds %= 60
    milliseconds = round((seconds - math.floor(seconds)) * 1000)
    return f"{int(hours):02d}:{int(minutes):02d}:{int(math.floor(seconds)):02d},{int(milliseconds):03d}"


def extract_audio(input_path: str, output_path: str) -> str:
    """
    Extract audio from a video file using FFmpeg.
    Returns the path to the extracted audio file.
    """
    logger.info(f'Extracting audio from video: {input_path}')

    try:
        result = subprocess.run(
            [
                'ffmpeg', '-y',
                '-i', input_path,
                '-vn',                  # No video
                '-acodec', 'pcm_s16le', # WAV format (best for Whisper)
                '-ar', '16000',         # 16kHz sample rate (optimal for Whisper)
                '-ac', '1',             # Mono
                output_path,
            ],
            capture_output=True,
            text=True,
            timeout=120,
        )

        if result.returncode != 0:
            raise RuntimeError(f'FFmpeg audio extraction failed: {result.stderr[-500:]}')

        if not os.path.exists(output_path) or os.path.getsize(output_path) == 0:
            raise RuntimeError('FFmpeg produced empty audio file')

        file_size = os.path.getsize(output_path)
        logger.info(f'Audio extracted: {file_size / 1024:.1f} KB')
        return output_path

    except subprocess.TimeoutExpired:
        raise RuntimeError('Audio extraction timed out (>120s)')


def _should_split_block(current_block: list, next_word: dict,
                        max_words: int = 12, max_duration: float = 4.0,
                        pause_threshold: float = 0.6) -> bool:
    """Decide whether to split the current subtitle block before adding next_word."""
    if not current_block:
        return False

    word_count = len(current_block)
    block_duration = next_word['end'] - current_block[0]['start']
    pause_gap = next_word['start'] - current_block[-1]['end']
    last_text = current_block[-1]['text']

    # Hard limits — always split
    if word_count >= max_words:
        return True
    if block_duration >= max_duration:
        return True

    # Pause in speech — split if block already has content
    if pause_gap >= pause_threshold and word_count >= 1:
        return True

    # Sentence-ending punctuation — split after . ! ? …
    if word_count >= 2 and last_text and last_text[-1] in '.!?…':
        return True

    # Clause-boundary punctuation — split after , ; : only if block is long enough
    if word_count >= 4 and last_text and last_text[-1] in ',;:':
        return True

    return False


def transcribe_to_srt(audio_path: str, output_path: str = None,
                      max_words_per_block: int = 12,
                      pause_threshold: float = 0.6,
                      max_block_duration: float = 4.0,
                      language: str = None) -> str:
    """
    Transcribe an audio file to SRT format using Whisper.
    Uses word-level timestamps, pauses and punctuation to create natural subtitle blocks.

    Args:
        audio_path: Path to the audio file (WAV, MP3, etc.)
        output_path: Optional output SRT file path.
        max_words_per_block: Hard cap on words per subtitle block (default: 12)
        pause_threshold: Seconds of silence to trigger a split (default: 0.6)
        max_block_duration: Max seconds a single subtitle stays on screen (default: 4.0)
        language: Optional language code (e.g. 'az', 'en', 'tr'). None = auto-detect.

    Returns:
        Path to the generated SRT file.
    """
    if not os.path.exists(audio_path):
        raise FileNotFoundError(f'Audio file not found: {audio_path}')

    if output_path is None:
        output_path = os.path.splitext(audio_path)[0] + '.srt'

    logger.info(f'Transcribing audio: {audio_path} (language={language or "auto"})')

    model = _get_model()

    # word_timestamps=True gives us per-word timing for precise subtitle blocks
    transcribe_kwargs = {'beam_size': 5, 'word_timestamps': True}
    if language:
        transcribe_kwargs['language'] = language

    segments, info = model.transcribe(audio_path, **transcribe_kwargs)

    logger.info(f'Detected language: {info.language} (confidence: {info.language_probability:.2f})')

    # Collect all words with timestamps
    all_words = []
    for segment in segments:
        if segment.words:
            for word in segment.words:
                all_words.append({
                    'text': word.word.strip(),
                    'start': word.start,
                    'end': word.end,
                })

    if not all_words:
        raise ValueError('No speech detected in the audio file. Cannot generate subtitles.')

    # Group words into natural blocks using pauses, punctuation and limits
    blocks = []
    current_block = []

    for word in all_words:
        if _should_split_block(current_block, word,
                               max_words=max_words_per_block,
                               max_duration=max_block_duration,
                               pause_threshold=pause_threshold):
            blocks.append({
                'start': current_block[0]['start'],
                'end': current_block[-1]['end'],
                'text': ' '.join(w['text'] for w in current_block),
            })
            current_block = []

        current_block.append(word)

    # Don't forget the last block
    if current_block:
        blocks.append({
            'start': current_block[0]['start'],
            'end': current_block[-1]['end'],
            'text': ' '.join(w['text'] for w in current_block),
        })

    # Write SRT file
    with open(output_path, 'w', encoding='utf-8') as f:
        for i, block in enumerate(blocks):
            start_time = _format_srt_time(block['start'])
            end_time = _format_srt_time(block['end'])
            f.write(f"{i + 1}\n")
            f.write(f"{start_time} --> {end_time}\n")
            f.write(f"{block['text']}\n\n")

    logger.info(f'Transcription complete: {len(blocks)} subtitle blocks → {output_path}')
    return output_path


def transcribe_from_video(video_path: str, temp_dir: str) -> str:
    """
    Full pipeline: Extract audio from video, then transcribe to SRT.

    Args:
        video_path: Path to the video file.
        temp_dir: Temporary directory for intermediate files.

    Returns:
        Path to the generated SRT file.
    """
    # Extract audio
    audio_path = os.path.join(temp_dir, 'whisper_audio.wav')
    extract_audio(video_path, audio_path)

    # Transcribe
    srt_path = os.path.join(temp_dir, 'auto_subtitles.srt')
    transcribe_to_srt(audio_path, srt_path)

    # Clean up intermediate audio
    try:
        os.remove(audio_path)
    except OSError:
        pass

    return srt_path


def transcribe_from_audio(audio_path: str, temp_dir: str) -> str:
    """
    Transcribe an audio file directly to SRT.

    Args:
        audio_path: Path to the audio file.
        temp_dir: Temporary directory for output.

    Returns:
        Path to the generated SRT file.
    """
    srt_path = os.path.join(temp_dir, 'auto_subtitles.srt')
    transcribe_to_srt(audio_path, srt_path)
    return srt_path
