"""
JSON2Video — Text Element Handler
"""
import logging
from PIL import ImageFont

from moviepy.editor import TextClip

from app.elements.base import BaseElement

logger = logging.getLogger('element.text')

# Default font paths (inside the Docker container)
DEFAULT_FONTS = [
    '/usr/share/fonts/truetype/montserrat/Montserrat-Black.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
]


def find_font(font_family: str = None) -> str:
    """Find a usable font file."""
    if font_family:
        # Try to match the requested font
        for path in DEFAULT_FONTS:
            if font_family.lower() in path.lower():
                return path

    # Return first available font
    import os
    for path in DEFAULT_FONTS:
        if os.path.exists(path):
            return path

    return 'DejaVu-Sans'  # MoviePy fallback


class TextElement(BaseElement):
    """Renders a text overlay as a video clip."""

    def render(self, temp_dir: str):
        """Create a TextClip with the specified properties."""
        text = self.data.get('text')
        if not text:
            raise ValueError('Text element requires a "text" field')

        font_size = self.data.get('font-size', 36)
        color = self.data.get('color', '#ffffff')
        font_family = self.data.get('font-family')
        bg_color = self.data.get('background-color')
        text_align = self.data.get('text-align', 'center')
        max_width = self.data.get('max-width')

        # Find font
        font = find_font(font_family)

        # Build TextClip kwargs
        clip_kwargs = {
            'fontsize': font_size,
            'color': color,
            'font': font,
            'method': 'caption' if max_width else 'label',
            'align': text_align,
        }

        if max_width:
            clip_kwargs['size'] = (max_width, None)

        if bg_color:
            clip_kwargs['bg_color'] = bg_color

        clip = TextClip(text, **clip_kwargs)

        # Set duration and timing
        clip = clip.set_duration(self.duration)
        clip = clip.set_start(self.start)
        clip = clip.set_position(self.get_position())

        # Apply opacity
        if self.opacity < 1.0:
            clip = clip.set_opacity(self.opacity)

        logger.info(f'Text element: "{text[:30]}...", size={font_size}, '
                     f'start={self.start}s, duration={self.duration}s')

        return clip
