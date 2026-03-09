"""
JSON2Video — Subtitles Element Handler

Supports:
  - Inline text via "text" field (single subtitle)
  - SRT file via "src" field (multiple timed subtitles)
"""
import logging
import re

from moviepy.editor import TextClip, CompositeVideoClip, ColorClip

from app.elements.base import BaseElement
from app.utils.downloader import download_asset

logger = logging.getLogger('element.subtitles')


def parse_srt(filepath: str) -> list:
    """
    Parse an SRT file and return a list of subtitle entries.
    Each entry: {'index': int, 'start': float, 'end': float, 'text': str}
    """
    with open(filepath, 'r', encoding='utf-8-sig') as f:
        content = f.read()

    # Split into blocks by double newline
    blocks = re.split(r'\n\s*\n', content.strip())
    entries = []

    for block in blocks:
        lines = block.strip().split('\n')
        if len(lines) < 3:
            continue

        # Line 1: index (skip)
        # Line 2: timestamp
        timestamp_line = lines[1].strip()
        match = re.match(
            r'(\d{2}):(\d{2}):(\d{2})[,.](\d{3})\s*-->\s*(\d{2}):(\d{2}):(\d{2})[,.](\d{3})',
            timestamp_line
        )
        if not match:
            continue

        h1, m1, s1, ms1, h2, m2, s2, ms2 = match.groups()
        start = int(h1) * 3600 + int(m1) * 60 + int(s1) + int(ms1) / 1000.0
        end = int(h2) * 3600 + int(m2) * 60 + int(s2) + int(ms2) / 1000.0

        # Lines 3+: subtitle text
        text = '\n'.join(line.strip() for line in lines[2:] if line.strip())

        # Remove HTML tags (e.g. <i>, <b>)
        text = re.sub(r'<[^>]+>', '', text)

        if text:
            entries.append({
                'index': len(entries) + 1,
                'start': start,
                'end': end,
                'text': text,
            })

    return entries


class SubtitlesElement(BaseElement):
    """Renders subtitles as a text overlay (inline or from SRT/ASS file)."""

    def render(self, temp_dir: str):
        """Create subtitle clips from text or SRT file."""
        text = self.data.get('text')
        src = self.data.get('src')

        font_size = self.data.get('font-size', 32)
        color = self.data.get('color', '#ffffff')
        bg_color = self.data.get('background', None)
        y_pos = self.data.get('y', self.resolution[1] - 100)

        if src:
            return self._render_srt(src, temp_dir, font_size, color, bg_color, y_pos)

        if not text:
            raise ValueError('Subtitles element requires "text" or "src" field')

        return self._render_text(text, font_size, color, bg_color, y_pos)

    def _render_text(self, text, font_size, color, bg_color, y_pos):
        """Render a single inline subtitle."""
        clip = self._make_text_clip(text, font_size, color, bg_color)

        x_pos = (self.resolution[0] - clip.w) // 2
        clip = clip.set_position((x_pos, y_pos))
        clip = clip.set_duration(self.duration)
        clip = clip.set_start(self.start)

        if self.opacity < 1.0:
            clip = clip.set_opacity(self.opacity)

        logger.info(f'Subtitles (inline): "{text[:40]}...", y={y_pos}')
        return clip

    def _render_srt(self, src, temp_dir, font_size, color, bg_color, y_pos):
        """Render SRT file as multiple timed subtitle clips."""
        logger.info(f'Subtitles from SRT: {src}')
        local_path = download_asset(src, temp_dir)
        entries = parse_srt(local_path)

        if not entries:
            raise ValueError(f'No valid subtitle entries found in SRT file: {src}')

        logger.info(f'Parsed {len(entries)} subtitle entries from SRT')

        clips = []
        element_start = self.start  # Element's start offset within the scene

        for entry in entries:
            sub_start = element_start + entry['start']
            sub_duration = entry['end'] - entry['start']

            # Skip if subtitle is outside this element's time window
            if sub_start >= element_start + self.duration:
                continue

            # Clamp duration to not exceed element's end
            max_duration = (element_start + self.duration) - sub_start
            sub_duration = min(sub_duration, max_duration)

            if sub_duration <= 0:
                continue

            clip = self._make_text_clip(entry['text'], font_size, color, bg_color)
            x_pos = (self.resolution[0] - clip.w) // 2
            clip = clip.set_position((x_pos, y_pos))
            clip = clip.set_start(sub_start)
            clip = clip.set_duration(sub_duration)

            if self.opacity < 1.0:
                clip = clip.set_opacity(self.opacity)

            clips.append(clip)
            logger.info(f'  Sub #{entry["index"]}: "{entry["text"][:30]}..." '
                        f'{sub_start:.1f}s-{sub_start + sub_duration:.1f}s')

        return clips

    def _make_text_clip(self, text, font_size, color, bg_color):
        """Create a single TextClip with the given style."""
        clip_kwargs = {
            'fontsize': font_size,
            'color': color,
            'font': '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            'method': 'caption',
            'size': (self.resolution[0] - 100, None),
            'align': 'center',
        }

        if bg_color:
            clip_kwargs['bg_color'] = bg_color

        return TextClip(text, **clip_kwargs)
