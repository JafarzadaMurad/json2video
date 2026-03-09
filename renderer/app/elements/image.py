"""
JSON2Video — Image Element Handler
"""
import logging

from moviepy.editor import ImageClip

from app.elements.base import BaseElement
from app.utils.downloader import download_asset

logger = logging.getLogger('element.image')


class ImageElement(BaseElement):
    """Renders a static image as a video clip."""

    def render(self, temp_dir: str):
        """Download the image and create an ImageClip."""
        src = self.data.get('src')
        if not src:
            raise ValueError('Image element requires a "src" field')

        # Download the image
        local_path = download_asset(src, temp_dir, allowed_types=['image'])

        # Create the clip
        clip = ImageClip(local_path)

        # Resize if dimensions specified
        target_w = self.width or self.resolution[0]
        target_h = self.height or self.resolution[1]
        clip = clip.resize((target_w, target_h))

        # Set duration and timing
        clip = clip.set_duration(self.duration)
        clip = clip.set_start(self.start)
        clip = clip.set_position(self.get_position())

        # Apply opacity if not 1.0
        if self.opacity < 1.0:
            clip = clip.set_opacity(self.opacity)

        logger.info(f'Image element: {target_w}x{target_h}, '
                     f'start={self.start}s, duration={self.duration}s')

        return clip
