"""
JSON2Video — Subtitles Element Handler

Supports:
  - Inline text via "text" field (single subtitle)
  - SRT file via "src" field (multiple timed subtitles)
  - Position: bottom, top, left, right (pixel offsets)
  - Animations: fade-in, fade-out, word-by-word, highlight, bounce
  - Stroke/outline for readability
"""
import logging
import os
import re

from moviepy.editor import TextClip, CompositeVideoClip, ColorClip, ImageClip, concatenate_videoclips
import numpy as np

from app.elements.base import BaseElement
from app.utils.downloader import download_asset

logger = logging.getLogger('element.subtitles')

# Auto-color palette for highlight-color: "auto"
AUTO_HIGHLIGHT_COLORS = [
    '#ffff00',  # yellow
    '#ff3333',  # red
    '#33ff57',  # green
    '#33ccff',  # blue
    '#ff8c1a',  # orange
    '#ff33ff',  # pink/magenta
    '#00ffcc',  # teal
    '#ff6699',  # rose
]


def _read_text_file(filepath: str) -> str:
    """Read a text file with encoding fallback."""
    for encoding in ('utf-8-sig', 'utf-8', 'latin-1', 'cp1251'):
        try:
            with open(filepath, 'r', encoding=encoding) as f:
                return f.read()
        except (UnicodeDecodeError, UnicodeError):
            continue
    raise ValueError(f'Cannot decode subtitle file (not a text file): {filepath}')


def detect_subtitle_format(filepath: str, content: str) -> str:
    """Detect subtitle format from content or extension."""
    ext = os.path.splitext(filepath)[1].lower()
    if ext in ('.ass', '.ssa'):
        return 'ass'
    if ext == '.srt':
        return 'srt'
    # Auto-detect from content
    if '[Script Info]' in content or 'ScriptType:' in content:
        return 'ass'
    if re.search(r'\d{2}:\d{2}:\d{2}[,.]\d{3}\s*-->\s*\d{2}:\d{2}:\d{2}[,.]\d{3}', content):
        return 'srt'
    return 'unknown'


def parse_srt(content: str) -> list:
    """Parse SRT content and return subtitle entries."""
    blocks = re.split(r'\n\s*\n', content.strip())
    entries = []

    for block in blocks:
        lines = block.strip().split('\n')
        if len(lines) < 3:
            continue

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

        text = '\n'.join(line.strip() for line in lines[2:] if line.strip())
        text = re.sub(r'<[^>]+>', '', text)

        if text:
            entries.append({
                'index': len(entries) + 1,
                'start': start,
                'end': end,
                'text': text,
            })

    return entries


def _parse_ass_time(time_str: str) -> float:
    """Parse ASS timestamp format: H:MM:SS.CC (flexible digit counts)"""
    match = re.match(r'(\d+):(\d+):(\d+)\.(\d+)', time_str.strip())
    if not match:
        return 0.0
    h, m, s, cs = match.groups()
    # Centiseconds: pad/truncate to 2 digits
    cs = cs[:2].ljust(2, '0')
    return int(h) * 3600 + int(m) * 60 + int(s) + int(cs) / 100.0


def parse_ass(content: str) -> list:
    """Parse ASS/SSA content and return subtitle entries."""
    entries = []
    in_events = False
    format_fields = None

    for line in content.split('\n'):
        line = line.strip()

        if line.lower() == '[events]':
            in_events = True
            continue

        if line.startswith('[') and in_events:
            break  # next section

        if not in_events:
            continue

        if line.lower().startswith('format:'):
            format_fields = [f.strip().lower() for f in line[7:].split(',')]
            continue

        if line.startswith('Dialogue:') and format_fields:
            # Split only up to the number of format fields
            parts = line[9:].split(',', len(format_fields) - 1)
            if len(parts) < len(format_fields):
                continue

            field_map = dict(zip(format_fields, parts))

            start = _parse_ass_time(field_map.get('start', '0:00:00.00'))
            end = _parse_ass_time(field_map.get('end', '0:00:00.00'))
            text = field_map.get('text', '').strip()

            # Remove ASS style tags like {\pos(x,y)} {\an8} {\b1}
            text = re.sub(r'\{[^}]*\}', '', text)
            # Replace \N with space (ASS line break)
            text = text.replace('\\N', ' ').replace('\\n', ' ')
            text = text.strip()

            if text and end > start:
                entries.append({
                    'index': len(entries) + 1,
                    'start': start,
                    'end': end,
                    'text': text,
                })

    # Sort by start time
    entries.sort(key=lambda e: e['start'])
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
            'highlight_color': self.data.get('highlight-color', None),
            'glow_spread': self.data.get('glow-spread', None),  # None = auto (font_size/3)
            'glow_blur': self.data.get('glow-blur', 20),
            'glow_opacity': self.data.get('glow-opacity', 200),
        }

        # ─── Position ──────────────────────────────
        position = self._resolve_position()

        # ─── Animation ─────────────────────────────
        animation = self.data.get('animation', None)
        # animation can be: "fade-in", "fade-out", "word-by-word", "highlight", "bounce" or dict

        if src:
            return self._render_subtitle_file(src, temp_dir, style, position, animation)

        if not text:
            raise ValueError('Subtitles element requires "text" or "src" field')

        return self._render_text(text, style, position, animation)

    def _resolve_position(self):
        """
        Calculate position from named positions and/or pixel offsets.

        Named positions:
          position-y: top | center | bottom (default: bottom)
          position-x: left | center | right (default: center)

        Pixel offsets (applied ON TOP of named position):
          top: N      → N px from top
          bottom: N   → N px from bottom edge
          left: N     → N px from left
          right: N    → N px from right

        If pixel offset is given without named position, it acts as the position.
        """
        canvas_w, canvas_h = self.resolution

        # ── Vertical ────────────────────────────
        pos_y = self.data.get('position-y', None)
        use_bottom = False
        bottom_offset = 0

        if pos_y == 'top':
            y = int(self.data.get('top', 50))  # named top + optional offset
        elif pos_y == 'center':
            y = 'center_y'  # special marker, resolved after clip creation
        elif pos_y == 'bottom' or pos_y is None:
            use_bottom = True
            bottom_offset = int(self.data.get('bottom', 100))
            y = None
        elif 'top' in self.data:
            y = int(self.data['top'])
        elif 'bottom' in self.data:
            use_bottom = True
            bottom_offset = int(self.data['bottom'])
            y = None
        elif 'y' in self.data:
            y = int(self.data['y'])
        else:
            use_bottom = True
            bottom_offset = 100
            y = None

        # ── Horizontal ──────────────────────────
        pos_x = self.data.get('position-x', 'center')
        align = pos_x if pos_x in ('left', 'center', 'right') else 'center'
        x_offset = 0
        if align == 'left':
            x_offset = int(self.data.get('left', 20))
        elif align == 'right':
            x_offset = int(self.data.get('right', 20))

        return {
            'y': y, 'align': align, 'x_offset': x_offset,
            'use_bottom': use_bottom, 'bottom_offset': bottom_offset,
        }

    def _get_x_pos(self, clip_width, position):
        """Calculate x position based on alignment."""
        canvas_w = self.resolution[0]
        if position['align'] == 'left':
            return position['x_offset']
        elif position['align'] == 'right':
            return canvas_w - clip_width - position['x_offset']
        else:
            return (canvas_w - clip_width) // 2

    def _get_y_pos(self, clip_height, position):
        """Calculate y position, using actual clip height for bottom/center anchoring."""
        if position['use_bottom']:
            canvas_h = self.resolution[1]
            return canvas_h - position['bottom_offset'] - clip_height
        if position['y'] == 'center_y':
            canvas_h = self.resolution[1]
            return (canvas_h - clip_height) // 2
        return position['y']

    def _render_text(self, text, style, position, animation):
        """Render a single inline subtitle."""
        clip = self._make_text_clip(text, style)

        x_pos = self._get_x_pos(clip.w, position)
        y_pos = self._get_y_pos(clip.h, position)
        clip = clip.set_position((x_pos, y_pos))
        clip = clip.set_duration(self.duration)
        clip = clip.set_start(self.start)

        if self.opacity < 1.0:
            clip = clip.set_opacity(self.opacity)

        clip = self._apply_subtitle_animation(clip, text, style, position, animation, self.start, self.duration)

        logger.info(f'Subtitles (inline): "{text[:40]}...", y={y_pos}')
        return clip

    def _render_subtitle_file(self, src, temp_dir, style, position, animation):
        """Render subtitle file (SRT or ASS) as multiple timed subtitle clips."""
        logger.info(f'Subtitles from file: {src}')
        local_path = download_asset(src, temp_dir)

        # Read file with encoding fallback
        try:
            content = _read_text_file(local_path)
        except ValueError as e:
            raise ValueError(f'Cannot read subtitle file: {e}. '
                             f'Make sure the URL points to an SRT or ASS file, not a video.')

        # Detect format
        fmt = detect_subtitle_format(local_path, content)
        logger.info(f'Detected subtitle format: {fmt}')

        if fmt == 'srt':
            entries = parse_srt(content)
        elif fmt == 'ass':
            entries = parse_ass(content)
        else:
            raise ValueError(
                f'Unknown subtitle format for: {src}. '
                f'Supported formats: .srt, .ass/.ssa'
            )

        if not entries:
            raise ValueError(f'No valid subtitle entries found in {fmt.upper()} file: {src}')

        logger.info(f'Parsed {len(entries)} subtitle entries from {fmt.upper()}')

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
                sub_start, sub_duration, entry_index=entry['index'] - 1
            )

            if isinstance(result, list):
                clips.extend(result)
            else:
                clips.append(result)

            logger.info(f'  Sub #{entry["index"]}: "{entry["text"][:30]}..." '
                        f'{sub_start:.1f}s-{sub_start + sub_duration:.1f}s')

        return clips

    def _apply_subtitle_animation(self, clip, text, style, position, animation, start_time, duration, entry_index=0):
        """Apply animation to subtitle clip. Returns clip or list of clips."""
        anim_type = None
        anim_params = {}

        if isinstance(animation, str):
            anim_type = animation
        elif isinstance(animation, dict):
            anim_type = animation.get('type')
            anim_params = animation

        # Resolve highlight-color: from animation params, style, or auto
        highlight_color = anim_params.get('highlight-color') or style.get('highlight_color')
        if highlight_color == 'auto':
            highlight_color = AUTO_HIGHLIGHT_COLORS[entry_index % len(AUTO_HIGHLIGHT_COLORS)]

        # ── word-by-word: each word appears one at a time ──
        if anim_type == 'word-by-word':
            if highlight_color:
                merged = {**anim_params, 'highlight-color': highlight_color}
                return self._anim_highlight(text, style, position, start_time, duration, merged)
            return self._anim_word_by_word(text, style, position, start_time, duration, anim_params)

        # ── highlight: karaoke word-by-word color change ──
        if anim_type == 'highlight':
            if highlight_color:
                anim_params = {**anim_params, 'highlight-color': highlight_color}
            return self._anim_highlight(text, style, position, start_time, duration, anim_params)

        # ── Build the text clip ──
        if clip is None:
            if highlight_color:
                # Styled rendering: glow + text as separate clips
                glow_img, text_img = self._render_styled_subtitle(text, style, highlight_color, entry_index)
                glow_opacity_frac = style.get('glow_opacity', 150) / 255.0

                # Glow clip: full alpha, opacity controlled by MoviePy
                glow_clip = ImageClip(np.array(glow_img))
                glow_clip = glow_clip.set_opacity(glow_opacity_frac)

                # Text clip: full alpha
                clip = ImageClip(np.array(text_img))

                # Stack glow behind text
                img_w, img_h = text_img.size
                combined = CompositeVideoClip([glow_clip, clip], size=(img_w, img_h))
                x_pos = self._get_x_pos(img_w, position)
                y_pos = self._get_y_pos(img_h, position)
                clip = combined
            else:
                clip = self._make_text_clip(text, style)
                x_pos = self._get_x_pos(clip.w, position)
                y_pos = self._get_y_pos(clip.h, position)
            clip = clip.set_position((x_pos, y_pos))
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
        elif anim_type == 'bounce':
            return self._anim_bounce(clip, start_time, duration, anim_params)

        return clip

    def _render_styled_subtitle(self, text, style, highlight_color, entry_index):
        """Render subtitle with random highlighted word, neon glow, and stroke."""
        import random
        from PIL import Image, ImageDraw, ImageFont, ImageFilter

        words = text.split()
        if not words:
            words = [text]

        # Pick 1 random word to highlight (deterministic per entry)
        rng = random.Random(entry_index * 7 + len(text))
        highlight_idx = rng.randint(0, len(words) - 1)

        font_path = style['font']
        if style['bold'] and 'Bold' not in font_path:
            bold_path = font_path.replace('.ttf', '-Bold.ttf')
            if os.path.exists(bold_path):
                font_path = bold_path

        font = ImageFont.truetype(font_path, style['font_size'])
        max_width = self.resolution[0] - 100
        # Base color = user's color, highlight = palette color
        base_color = style['color']
        stroke_color = style.get('stroke_color') or '#000000'
        stroke_width = style.get('stroke_width', 2)

        # Word-wrap layout
        lines = []
        current_line = []
        current_width = 0
        space_w = font.getlength(' ')

        for idx, word in enumerate(words):
            w = font.getlength(word)
            needed = w + (space_w if current_line else 0)
            if current_line and current_width + needed > max_width:
                lines.append(current_line)
                current_line = [(word, idx)]
                current_width = w
            else:
                current_line.append((word, idx))
                current_width += needed
        if current_line:
            lines.append(current_line)

        # Image dimensions
        line_height = int(font.size * 1.4)
        glow_pad = 50  # enough room for spread + blur
        padding = stroke_width + glow_pad
        img_h = len(lines) * line_height + padding * 2
        img_w = max_width + glow_pad * 2

        def get_word_color(idx):
            return highlight_color if idx == highlight_idx else base_color

        # ── 1) Glow layer: thick colored stroke + strong blur ──
        font_sz = style.get('font_size', 32)
        glow_spread = style.get('glow_spread') or max(int(font_sz / 3), 15)
        glow_blur = style.get('glow_blur', 20)
        # glow_opacity handled by caller via set_opacity()

        glow_img = Image.new('RGBA', (img_w, img_h), (0, 0, 0, 0))
        glow_draw = ImageDraw.Draw(glow_img)

        y = padding
        for line in lines:
            line_text = ' '.join(w for w, _ in line)
            total_w = font.getlength(line_text)
            x = (img_w - total_w) / 2
            for word, idx in line:
                color = get_word_color(idx)
                glow_draw.text((x, y), word, font=font, fill=color,
                               stroke_width=glow_spread, stroke_fill=color)
                x += font.getlength(word + ' ')
            y += line_height

        glow_img = glow_img.filter(ImageFilter.GaussianBlur(radius=glow_blur))

        # Force full alpha for any visible glow pixel (binary: 255 or 0)
        r, g, b, a = glow_img.split()
        a = a.point(lambda p: 255 if p > 5 else 0)
        glow_img = Image.merge('RGBA', (r, g, b, a))

        # ── 2) Text layer: sharp text with black stroke, full alpha ──
        text_img = Image.new('RGBA', (img_w, img_h), (0, 0, 0, 0))
        text_draw = ImageDraw.Draw(text_img)
        thick_stroke = max(stroke_width, 3)

        y = padding
        for line in lines:
            line_text = ' '.join(w for w, _ in line)
            total_w = font.getlength(line_text)
            x = (img_w - total_w) / 2
            for word, idx in line:
                color = get_word_color(idx)
                text_draw.text((x, y), word, font=font, fill=color,
                               stroke_width=thick_stroke, stroke_fill='#000000')
                x += font.getlength(word + ' ')
            y += line_height

        logger.info('GLOW_DEBUG: spread=%s blur=%s size=%sx%s glow_nonzero=%s text_nonzero=%s',
                    glow_spread, glow_blur, img_w, img_h,
                    sum(1 for p in glow_img.split()[3].getdata() if p > 0),
                    sum(1 for p in text_img.split()[3].getdata() if p > 0))

        return glow_img, text_img

    def _anim_bounce(self, clip, start_time, duration, params):
        """Bounce-in animation: subtitle pops in-place with subtle bounce."""
        bounce_dur = params.get('duration', 0.3)
        bounce_dur = min(bounce_dur, duration * 0.5)

        orig_w, orig_h = clip.size
        orig_pos = clip.pos  # position function

        def bounce_scale(t):
            """Scale: 0.8 → 1.08 overshoot → 1.0 settle"""
            if t >= bounce_dur:
                return 1.0
            progress = t / bounce_dur
            if progress < 0.5:
                # Scale up from 0.8 to 1.08
                return 0.8 + (1.08 - 0.8) * (progress / 0.5)
            else:
                # Settle from 1.08 to 1.0
                settle = (progress - 0.5) / 0.5
                return 1.08 - 0.08 * settle

        def centered_pos(t):
            """Adjust position to keep clip centered during scale."""
            scale = bounce_scale(t)
            # Get original position
            if callable(orig_pos):
                ox, oy = orig_pos(t)
            else:
                ox, oy = orig_pos
            # Compensate: shift by half the size difference
            dx = (orig_w * (1 - scale)) / 2
            dy = (orig_h * (1 - scale)) / 2
            return (ox + dx, oy + dy)

        clip = clip.resize(lambda t: bounce_scale(t))
        clip = clip.set_position(centered_pos)
        return clip

    def _anim_word_by_word(self, text, style, position, start_time, duration, params):
        """Words appear one at a time. Full text rendered, future words invisible."""
        words = text.split()
        if not words:
            return []

        clips = []
        time_per_word = duration / len(words)
        fade_in = params.get('fade', 0)

        # Render first frame to get dimensions for positioning
        first_img = self._render_word_visibility_image(words, 1, style)
        x_pos = self._get_x_pos(first_img.width, position)
        y_pos = self._get_y_pos(first_img.height, position)

        for i in range(len(words)):
            word_start = start_time + i * time_per_word
            remaining = duration - i * time_per_word
            if remaining <= 0:
                continue

            # Render full text with words 0..i visible, rest transparent
            img = self._render_word_visibility_image(words, i + 1, style)
            clip = ImageClip(np.array(img)).set_duration(remaining)
            clip = clip.set_position((x_pos, y_pos))
            clip = clip.set_start(word_start)

            if fade_in > 0:
                clip = clip.crossfadein(min(fade_in, time_per_word / 2))

            if self.opacity < 1.0:
                clip = clip.set_opacity(self.opacity)

            clips.append(clip)

        return clips

    def _render_word_visibility_image(self, words, visible_count, style):
        """Render full text where first N words are visible, rest are transparent."""
        from PIL import Image, ImageDraw, ImageFont

        font_path = style['font']
        if style['bold'] and 'Bold' not in font_path:
            bold_path = font_path.replace('.ttf', '-Bold.ttf')
            if os.path.exists(bold_path):
                font_path = bold_path

        font = ImageFont.truetype(font_path, style['font_size'])
        max_width = self.resolution[0] - 100
        base_color = style['color']
        stroke_color = style.get('stroke_color')
        stroke_width = style.get('stroke_width', 2) if stroke_color else 0

        # Word-wrap layout
        lines = []
        current_line = []
        current_width = 0
        space_w = font.getlength(' ')

        for idx, word in enumerate(words):
            w = font.getlength(word)
            needed = w + (space_w if current_line else 0)
            if current_line and current_width + needed > max_width:
                lines.append(current_line)
                current_line = [(word, idx)]
                current_width = w
            else:
                current_line.append((word, idx))
                current_width += needed
        if current_line:
            lines.append(current_line)

        # Image dimensions
        line_height = int(font.size * 1.4)
        padding = stroke_width + 2
        img_h = len(lines) * line_height + padding * 2
        img_w = max_width

        img = Image.new('RGBA', (img_w, img_h), (0, 0, 0, 0))
        draw = ImageDraw.Draw(img)

        y = padding
        for line in lines:
            line_text = ' '.join(w for w, _ in line)
            total_w = font.getlength(line_text)
            x = (img_w - total_w) / 2  # center

            for word, idx in line:
                # Visible if word index < visible_count, else fully transparent
                if idx < visible_count:
                    color = base_color
                    s_color = stroke_color
                else:
                    color = (0, 0, 0, 0)  # transparent
                    s_color = None

                if s_color and stroke_width > 0:
                    draw.text((x, y), word, font=font, fill=color,
                              stroke_width=stroke_width, stroke_fill=s_color)
                else:
                    draw.text((x, y), word, font=font, fill=color)

                x += font.getlength(word + ' ')
            y += line_height

        return img

    def _anim_highlight(self, text, style, position, start_time, duration, params):
        """True karaoke: single image per frame, words progressively change color."""
        words = text.split()
        if not words:
            return []

        highlight_color = params.get('highlight-color', '#ffff00')
        clips = []
        time_per_word = duration / len(words)

        for i in range(len(words)):
            word_start = start_time + i * time_per_word
            remaining = duration - i * time_per_word
            if remaining <= 0:
                continue

            # Render single image: words 0..i in highlight_color, rest in base color
            img = self._render_karaoke_image(words, i + 1, style, highlight_color)
            clip = ImageClip(np.array(img)).set_duration(remaining)

            x_pos = self._get_x_pos(img.width, position)
            y_pos = self._get_y_pos(img.height, position)
            clip = clip.set_position((x_pos, y_pos))
            clip = clip.set_start(word_start)

            if self.opacity < 1.0:
                clip = clip.set_opacity(self.opacity)

            clips.append(clip)

        return clips

    def _render_karaoke_image(self, words, highlight_count, style, highlight_color):
        """Render text image with per-word coloring using Pillow."""
        from PIL import Image, ImageDraw, ImageFont

        font_path = style['font']
        if style['bold'] and 'Bold' not in font_path:
            bold_path = font_path.replace('.ttf', '-Bold.ttf')
            import os as _os
            if _os.path.exists(bold_path):
                font_path = bold_path

        font_size = style['font_size']
        font = ImageFont.truetype(font_path, font_size)
        max_width = self.resolution[0] - 100
        base_color = style['color']
        stroke_color = style.get('stroke_color')
        stroke_width = style.get('stroke_width', 2) if stroke_color else 0

        # Word-wrap: split words into lines
        lines = []  # each line: list of (word, is_highlighted)
        current_line = []
        current_width = 0
        space_width = font.getlength(' ')

        for idx, word in enumerate(words):
            word_width = font.getlength(word)
            needed = word_width + (space_width if current_line else 0)

            if current_line and current_width + needed > max_width:
                lines.append(current_line)
                current_line = [(word, idx < highlight_count)]
                current_width = word_width
            else:
                current_line.append((word, idx < highlight_count))
                current_width += needed

        if current_line:
            lines.append(current_line)

        # Calculate image dimensions
        line_height = int(font_size * 1.4)
        padding = stroke_width + 2
        img_height = len(lines) * line_height + padding * 2
        img_width = max_width

        # Create transparent image
        img = Image.new('RGBA', (img_width, img_height), (0, 0, 0, 0))
        draw = ImageDraw.Draw(img)

        # Center each line horizontally
        y = padding
        for line in lines:
            # Calculate total line width
            line_text = ' '.join(w for w, _ in line)
            total_width = font.getlength(line_text)
            x = (img_width - total_width) / 2  # center

            for word, is_highlighted in line:
                color = highlight_color if is_highlighted else base_color

                if stroke_color and stroke_width > 0:
                    draw.text((x, y), word, font=font, fill=color,
                              stroke_width=stroke_width, stroke_fill=stroke_color)
                else:
                    draw.text((x, y), word, font=font, fill=color)

                x += font.getlength(word + ' ')

            y += line_height

        return img

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
