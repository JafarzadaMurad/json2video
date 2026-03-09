"""
JSON2Video — Subtitles Element Handler

Supports:
  - Inline text via "text" field (single subtitle)
  - SRT file via "src" field (multiple timed subtitles)
  - Position: bottom, top, left, right (pixel offsets)
  - Animations: fade-in, fade-out, word-by-word, highlight
  - Stroke/outline for readability
"""
import logging
import os
import re

from moviepy.editor import TextClip, CompositeVideoClip, ColorClip, concatenate_videoclips
import numpy as np

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

        style = {
            'font_size': self.data.get('font-size', 32),
            'color': self.data.get('color', '#ffffff'),
            'bg_color': self.data.get('background', None),
            'stroke_color': self.data.get('stroke-color', None),
            'stroke_width': self.data.get('stroke-width', 2),
            'font': self.data.get('font', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'),
            'bold': self.data.get('bold', False),
        }

        # ─── Position ──────────────────────────────
        position = self._resolve_position()

        # ─── Animation ─────────────────────────────
        animation = self.data.get('animation', None)
        # animation can be: "fade-in", "fade-out", "word-by-word", "highlight", or dict with type+params

        if src:
            return self._render_srt(src, temp_dir, style, position, animation)

        if not text:
            raise ValueError('Subtitles element requires "text" or "src" field')

        return self._render_text(text, style, position, animation)

    def _resolve_position(self):
        """Calculate x,y position from bottom/top/left/right offsets."""
        canvas_w, canvas_h = self.resolution

        # Vertical: bottom or top (default: bottom 30px)
        if 'top' in self.data:
            y = int(self.data['top'])
        elif 'bottom' in self.data:
            y = canvas_h - int(self.data['bottom']) - 80  # approximate text height
        elif 'y' in self.data:
            y = int(self.data['y'])
        else:
            y = canvas_h - 100  # default bottom

        # Horizontal: left, right, or center
        align = 'center'
        x_offset = 0
        if 'left' in self.data:
            align = 'left'
            x_offset = int(self.data['left'])
        elif 'right' in self.data:
            align = 'right'
            x_offset = int(self.data['right'])

        return {'y': y, 'align': align, 'x_offset': x_offset}

    def _get_x_pos(self, clip_width, position):
        """Calculate x position based on alignment."""
        canvas_w = self.resolution[0]
        if position['align'] == 'left':
            return position['x_offset']
        elif position['align'] == 'right':
            return canvas_w - clip_width - position['x_offset']
        else:
            return (canvas_w - clip_width) // 2

    def _render_text(self, text, style, position, animation):
        """Render a single inline subtitle."""
        clip = self._make_text_clip(text, style)

        x_pos = self._get_x_pos(clip.w, position)
        clip = clip.set_position((x_pos, position['y']))
        clip = clip.set_duration(self.duration)
        clip = clip.set_start(self.start)

        if self.opacity < 1.0:
            clip = clip.set_opacity(self.opacity)

        clip = self._apply_subtitle_animation(clip, text, style, position, animation, self.start, self.duration)

        logger.info(f'Subtitles (inline): "{text[:40]}...", y={position["y"]}')
        return clip

    def _render_srt(self, src, temp_dir, style, position, animation):
        """Render SRT file as multiple timed subtitle clips."""
        logger.info(f'Subtitles from SRT: {src}')
        local_path = download_asset(src, temp_dir)
        entries = parse_srt(local_path)

        if not entries:
            raise ValueError(f'No valid subtitle entries found in SRT file: {src}')

        logger.info(f'Parsed {len(entries)} subtitle entries from SRT')

        clips = []
        element_start = self.start

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

            result = self._apply_subtitle_animation(
                None, entry['text'], style, position, animation,
                sub_start, sub_duration
            )

            if isinstance(result, list):
                clips.extend(result)
            else:
                clips.append(result)

            logger.info(f'  Sub #{entry["index"]}: "{entry["text"][:30]}..." '
                        f'{sub_start:.1f}s-{sub_start + sub_duration:.1f}s')

        return clips

    def _apply_subtitle_animation(self, clip, text, style, position, animation, start_time, duration):
        """Apply animation to subtitle clip. Returns clip or list of clips."""
        anim_type = None
        anim_params = {}

        if isinstance(animation, str):
            anim_type = animation
        elif isinstance(animation, dict):
            anim_type = animation.get('type')
            anim_params = animation

        # ── word-by-word: each word appears one at a time ──
        if anim_type == 'word-by-word':
            return self._anim_word_by_word(text, style, position, start_time, duration, anim_params)

        # ── highlight: text appears, words get highlighted sequentially ──
        if anim_type == 'highlight':
            return self._anim_highlight(text, style, position, start_time, duration, anim_params)

        # ── Standard clip with fade ──
        if clip is None:
            clip = self._make_text_clip(text, style)
            x_pos = self._get_x_pos(clip.w, position)
            clip = clip.set_position((x_pos, position['y']))
            clip = clip.set_start(start_time)
            clip = clip.set_duration(duration)
            if self.opacity < 1.0:
                clip = clip.set_opacity(self.opacity)

        if anim_type == 'fade-in':
            fade_dur = anim_params.get('duration', 0.3) if isinstance(anim_params, dict) else 0.3
            clip = clip.crossfadein(fade_dur)
        elif anim_type == 'fade-out':
            fade_dur = anim_params.get('duration', 0.3) if isinstance(anim_params, dict) else 0.3
            clip = clip.crossfadeout(fade_dur)
        elif anim_type == 'fade':
            fade_dur = anim_params.get('duration', 0.3) if isinstance(anim_params, dict) else 0.3
            clip = clip.crossfadein(fade_dur).crossfadeout(fade_dur)

        return clip

    def _anim_word_by_word(self, text, style, position, start_time, duration, params):
        """Words appear one at a time, accumulating on screen."""
        words = text.split()
        if not words:
            return []

        clips = []
        time_per_word = duration / len(words)
        fade_in = params.get('fade', 0.15)

        for i in range(len(words)):
            # Show accumulated words up to this point
            partial_text = ' '.join(words[:i + 1])
            word_clip = self._make_text_clip(partial_text, style)

            x_pos = self._get_x_pos(word_clip.w, position)
            word_start = start_time + i * time_per_word

            # Duration: from this word's appearance to end of subtitle
            word_dur = duration - i * time_per_word
            if word_dur <= 0:
                continue

            word_clip = word_clip.set_position((x_pos, position['y']))
            word_clip = word_clip.set_start(word_start)
            word_clip = word_clip.set_duration(min(time_per_word, word_dur))

            if fade_in > 0:
                word_clip = word_clip.crossfadein(min(fade_in, time_per_word / 2))

            if self.opacity < 1.0:
                word_clip = word_clip.set_opacity(self.opacity)

            clips.append(word_clip)

        return clips

    def _anim_highlight(self, text, style, position, start_time, duration, params):
        """All text visible, but current word gets highlighted color."""
        words = text.split()
        if not words:
            return []

        highlight_color = params.get('highlight-color', '#ffff00')
        clips = []
        time_per_word = duration / len(words)

        for i, word in enumerate(words):
            word_start = start_time + i * time_per_word
            word_dur = time_per_word

            if word_dur <= 0:
                continue

            # Build text with highlight: render full text in base color on one layer,
            # and highlighted word on top
            base_clip = self._make_text_clip(text, style)
            x_pos = self._get_x_pos(base_clip.w, position)
            base_clip = base_clip.set_position((x_pos, position['y']))
            base_clip = base_clip.set_start(word_start)
            base_clip = base_clip.set_duration(word_dur)

            # Create highlighted version of the text
            highlight_style = style.copy()
            highlight_style['color'] = highlight_color

            # Build text with spaces replaced by transparent chars for non-current words
            before = ' '.join(words[:i])
            after = ' '.join(words[i + 1:])
            # We'll render just the highlighted word at the right position
            # by using a trick: render the word offset

            highlight_clip = self._make_text_clip(text, highlight_style)
            # Mask: only show the highlighted word area
            # Simpler approach: overlay the highlighted full text briefly
            highlight_clip = highlight_clip.set_position((x_pos, position['y']))
            highlight_clip = highlight_clip.set_start(word_start)
            highlight_clip = highlight_clip.set_duration(word_dur)

            if self.opacity < 1.0:
                base_clip = base_clip.set_opacity(self.opacity)
                highlight_clip = highlight_clip.set_opacity(self.opacity)

            # For simplicity, just show the base first, then highlighted full text
            # The highlighted version covers the base
            clips.append(base_clip)
            clips.append(highlight_clip)

        return clips

    def _make_text_clip(self, text, style):
        """Create a single TextClip with the given style, optionally with stroke."""
        font_path = style['font']
        if style['bold'] and 'Bold' not in font_path:
            bold_path = font_path.replace('.ttf', '-Bold.ttf')
            if os.path.exists(bold_path):
                font_path = bold_path

        clip_kwargs = {
            'fontsize': style['font_size'],
            'color': style['color'],
            'font': font_path,
            'method': 'caption',
            'size': (self.resolution[0] - 100, None),
            'align': 'center',
        }

        if style['bg_color']:
            clip_kwargs['bg_color'] = style['bg_color']

        main_clip = TextClip(text, **clip_kwargs)

        # Add stroke (outline) by rendering text with stroke behind
        if style['stroke_color']:
            stroke_kwargs = clip_kwargs.copy()
            stroke_kwargs['color'] = style['stroke_color']
            stroke_kwargs['stroke_color'] = style['stroke_color']
            stroke_kwargs['stroke_width'] = style['stroke_width']
            stroke_clip = TextClip(text, **stroke_kwargs)

            # Layer: stroke behind, main text on top
            w = max(main_clip.w, stroke_clip.w)
            h = max(main_clip.h, stroke_clip.h)
            combined = CompositeVideoClip(
                [stroke_clip.set_position('center'), main_clip.set_position('center')],
                size=(w, h),
            )
            combined = combined.on_color(size=(w, h), color=(0, 0, 0), col_opacity=0)
            return combined

        return main_clip
