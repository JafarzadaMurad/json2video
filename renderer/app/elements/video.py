"""
JSON2Video — Video Element Handler
"""
import logging

from moviepy.editor import VideoFileClip

from app.elements.base import BaseElement
from app.utils.downloader import download_asset

logger = logging.getLogger('element.video')


class VideoElement(BaseElement):
    """Embeds a video clip as an element."""

    def render(self, temp_dir: str):
        """Download video and create a VideoFileClip."""
        src = self.data.get('src')
        if not src:
            raise ValueError('Video element requires a "src" field')

        volume = self.data.get('volume', 1.0)
        mute = self.data.get('mute', False)
        trim_start = self.data.get('trim-start', 0)
        trim_end = self.data.get('trim-end')
        playback_rate = self.data.get('playback-rate', 1.0)

        # Download the video
        local_path = download_asset(src, temp_dir, allowed_types=['video'])

        # Create the clip
        clip = VideoFileClip(local_path)

        # Trim if specified
        if trim_start > 0 or trim_end:
            end = trim_end if trim_end else clip.duration
            clip = clip.subclip(trim_start, end)

        # Apply playback rate
        if playback_rate != 1.0:
            clip = clip.fx(lambda c: c.speedx(playback_rate))

        # Limit duration to scene duration
        if self.duration and self.duration < clip.duration:
            clip = clip.subclip(0, self.duration)

        # ─── Resize logic ─────────────────────────────
        # If both width AND height explicitly set → resize to exact dimensions
        # If only one set → scale proportionally
        # If neither set → fit within canvas keeping aspect ratio (no stretch)
        orig_w, orig_h = clip.size
        canvas_w, canvas_h = self.resolution

        if self.width and self.height:
            # User specified exact size
            target_w, target_h = self.width, self.height
        elif self.width:
            # Scale proportionally by width
            scale = self.width / orig_w
            target_w = self.width
            target_h = int(orig_h * scale)
        elif self.height:
            # Scale proportionally by height
            scale = self.height / orig_h
            target_w = int(orig_w * scale)
            target_h = self.height
        else:
            # No dimensions specified → fit within canvas keeping aspect ratio
            scale_w = canvas_w / orig_w
            scale_h = canvas_h / orig_h
            scale = min(scale_w, scale_h)  # Fit (don't crop)
            target_w = int(orig_w * scale)
            target_h = int(orig_h * scale)

        clip = clip.resize((target_w, target_h))

        # Set timing
        clip = clip.set_start(self.start)

        # Position: auto-center if no explicit x/y given, otherwise use specified position
        has_explicit_x = 'x' in self.data
        has_explicit_y = 'y' in self.data

        if not has_explicit_x and not has_explicit_y:
            # Auto-center both axes
            clip = clip.set_position('center')
        elif not has_explicit_x:
            # Center horizontally, use explicit y
            x_center = (self.resolution[0] - target_w) // 2
            clip = clip.set_position((x_center, self.y))
        elif not has_explicit_y:
            # Use explicit x, center vertically
            y_center = (self.resolution[1] - target_h) // 2
            clip = clip.set_position((self.x, y_center))
        else:
            clip = clip.set_position(self.get_position())

        # Audio handling
        if mute:
            clip = clip.without_audio()
        elif volume != 1.0 and clip.audio:
            clip = clip.volumex(volume)

        # Apply opacity
        if self.opacity < 1.0:
            clip = clip.set_opacity(self.opacity)

        logger.info(f'Video element: {orig_w}x{orig_h} → {target_w}x{target_h}, '
                     f'start={self.start}s, duration={clip.duration:.1f}s, '
                     f'mute={mute}, has_audio={clip.audio is not None}')

        return clip
