"""
JSON2Video — Audio Element Handler
"""
import logging

from moviepy.editor import AudioFileClip

from app.elements.base import BaseElement
from app.utils.downloader import download_asset

logger = logging.getLogger('element.audio')


class AudioElement(BaseElement):
    """Renders a background audio/music track."""

    def render(self, temp_dir: str):
        """Download audio and create an AudioFileClip."""
        src = self.data.get('src')
        if not src:
            raise ValueError('Audio element requires a "src" field')

        volume = self.data.get('volume', 1.0)
        loop = self.data.get('loop', False)

        # Download the audio file
        local_path = download_asset(src, temp_dir, allowed_types=['audio'])

        # Create the audio clip
        clip = AudioFileClip(local_path)

        # Trim if duration is specified
        if self.duration and self.duration < clip.duration:
            clip = clip.subclip(0, self.duration)

        # Set start time
        if self.start > 0:
            clip = clip.set_start(self.start)

        # Apply volume
        if volume != 1.0:
            clip = clip.volumex(volume)

        logger.info(f'Audio element: volume={volume}, '
                     f'start={self.start}s, duration={clip.duration:.1f}s')

        return clip
