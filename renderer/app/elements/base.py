"""
JSON2Video — Base Element Handler

Abstract base class for all element handlers.
"""
from abc import ABC, abstractmethod


class BaseElement(ABC):
    """Abstract base class for video elements."""

    def __init__(self, element_data: dict, scene_duration: float, resolution: tuple):
        self.data = element_data
        self.scene_duration = scene_duration
        self.resolution = resolution  # (width, height)

        # Common properties
        self.x = element_data.get('x', 0)
        self.y = element_data.get('y', 0)
        self.width = element_data.get('width')
        self.height = element_data.get('height')
        self.start = element_data.get('start', 0)
        self.duration = element_data.get('duration', scene_duration)
        self.opacity = element_data.get('opacity', 1.0)

    @abstractmethod
    def render(self, temp_dir: str):
        """
        Render this element and return a MoviePy clip.
        Must be implemented by subclasses.
        """
        pass

    def get_position(self):
        """Return the (x, y) position tuple."""
        return (self.x, self.y)
