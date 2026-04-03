"""
JSON2Video — Visual Effects for Image/Video Elements

Applies continuous visual effects like zoom, pan, and Ken Burns
to video/image clips using frame-level crop + resize transformations.
"""
import logging
import numpy as np

logger = logging.getLogger('effects.visual')


# ─── Easing Functions ─────────────────────────────

def _ease_linear(t):
    return t

def _ease_in(t):
    return t * t

def _ease_out(t):
    return 1 - (1 - t) * (1 - t)

def _ease_in_out(t):
    return 3 * t * t - 2 * t * t * t

EASING_MAP = {
    'linear': _ease_linear,
    'ease-in': _ease_in,
    'ease-out': _ease_out,
    'ease-in-out': _ease_in_out,
}


def apply_effect(clip, effect_config: dict, element_duration: float):
    """
    Apply a visual effect to a clip.

    Args:
        clip: MoviePy video/image clip (must already be resized to final size)
        effect_config: dict with at least 'type', plus optional params
        element_duration: the element's duration in seconds

    Returns:
        Modified clip with the effect applied as a frame transform.
    """
    effect_type = effect_config.get('type', '')
    duration = float(effect_config.get('duration', element_duration))
    easing_name = effect_config.get('easing', 'ease-in-out')
    easing_fn = EASING_MAP.get(easing_name, _ease_in_out)

    # Clamp effect duration to element duration
    duration = min(duration, element_duration)

    if effect_type == 'zoom-in':
        return _zoom_effect(clip, effect_config, duration, easing_fn, direction='in')
    elif effect_type == 'zoom-out':
        return _zoom_effect(clip, effect_config, duration, easing_fn, direction='out')
    elif effect_type == 'pan':
        return _pan_effect(clip, effect_config, duration, easing_fn)
    elif effect_type == 'ken-burns':
        return _ken_burns_effect(clip, effect_config, duration, easing_fn)
    else:
        logger.warning(f'Unknown effect type: {effect_type}, skipping')
        return clip


def _zoom_effect(clip, config, duration, easing_fn, direction='in'):
    """
    Zoom in or out on a clip.
    Works by rendering at a larger size and cropping to the center.
    """
    start_scale = float(config.get('start-scale', 1.0 if direction == 'in' else 1.3))
    end_scale = float(config.get('end-scale', 1.3 if direction == 'in' else 1.0))

    clip_w, clip_h = clip.size
    total_duration = clip.duration

    def zoom_filter(get_frame, t):
        # Calculate progress within effect duration
        if t < duration:
            progress = easing_fn(t / duration)
        else:
            progress = 1.0

        scale = start_scale + (end_scale - start_scale) * progress

        frame = get_frame(t)
        h, w = frame.shape[:2]

        # Calculate crop region (center crop)
        new_w = int(w / scale)
        new_h = int(h / scale)

        # Clamp to valid sizes
        new_w = max(2, min(new_w, w))
        new_h = max(2, min(new_h, h))

        x_offset = (w - new_w) // 2
        y_offset = (h - new_h) // 2

        cropped = frame[y_offset:y_offset + new_h, x_offset:x_offset + new_w]

        # Resize back to original dimensions
        from PIL import Image
        img = Image.fromarray(cropped)
        img = img.resize((w, h), Image.LANCZOS)
        return np.array(img)

    logger.info(f'Effect: zoom-{direction} ({start_scale}→{end_scale}, {duration}s)')
    return clip.fl(zoom_filter)


def _pan_effect(clip, config, duration, easing_fn):
    """
    Pan across a clip in a direction.
    Works by rendering at a larger size and sliding the crop window.
    """
    direction = config.get('direction', 'left')

    # Pan intensity: how much of the frame to pan across (as ratio of frame size)
    intensity = float(config.get('intensity', 0.15))

    clip_w, clip_h = clip.size

    def pan_filter(get_frame, t):
        if t < duration:
            progress = easing_fn(t / duration)
        else:
            progress = 1.0

        frame = get_frame(t)
        h, w = frame.shape[:2]

        # Create a slightly larger canvas
        pan_pixels_x = int(w * intensity)
        pan_pixels_y = int(h * intensity)

        # Calculate offset based on direction
        if direction == 'left':
            x_off = int(pan_pixels_x * (1 - progress))
            y_off = pan_pixels_y // 2
        elif direction == 'right':
            x_off = int(pan_pixels_x * progress)
            y_off = pan_pixels_y // 2
        elif direction == 'up':
            x_off = pan_pixels_x // 2
            y_off = int(pan_pixels_y * (1 - progress))
        elif direction == 'down':
            x_off = pan_pixels_x // 2
            y_off = int(pan_pixels_y * progress)
        else:
            x_off = 0
            y_off = 0

        # We need the source frame to be bigger than the output
        # Scale up, then crop with offset
        scale = 1 + intensity
        new_w = int(w * scale)
        new_h = int(h * scale)

        from PIL import Image
        img = Image.fromarray(frame)
        img_large = img.resize((new_w, new_h), Image.LANCZOS)
        large_arr = np.array(img_large)

        # Clamp offsets
        x_off = max(0, min(x_off, new_w - w))
        y_off = max(0, min(y_off, new_h - h))

        cropped = large_arr[y_off:y_off + h, x_off:x_off + w]
        return cropped

    logger.info(f'Effect: pan-{direction} (intensity={intensity}, {duration}s)')
    return clip.fl(pan_filter)


def _ken_burns_effect(clip, config, duration, easing_fn):
    """
    Ken Burns effect: simultaneous zoom + pan.
    Creates a cinematic slow zoom with drift.
    """
    zoom_direction = config.get('direction', 'in')
    x_anchor = config.get('x', 'center')  # left, center, right
    y_anchor = config.get('y', 'center')  # top, center, bottom

    start_scale = float(config.get('start-scale', 1.0 if zoom_direction == 'in' else 1.3))
    end_scale = float(config.get('end-scale', 1.3 if zoom_direction == 'in' else 1.0))

    clip_w, clip_h = clip.size

    # Anchor positions: determine where the crop center drifts to/from
    x_positions = {'left': 0.3, 'center': 0.5, 'right': 0.7}
    y_positions = {'top': 0.3, 'center': 0.5, 'bottom': 0.7}

    # For Ken Burns, we drift from anchor toward center (or vice versa)
    x_start = x_positions.get(x_anchor, 0.5)
    y_start = y_positions.get(y_anchor, 0.5)
    x_end = 0.5
    y_end = 0.5

    # If zooming out, reverse the drift
    if zoom_direction == 'out':
        x_start, x_end = x_end, x_start
        y_start, y_end = y_end, y_start

    def ken_burns_filter(get_frame, t):
        if t < duration:
            progress = easing_fn(t / duration)
        else:
            progress = 1.0

        scale = start_scale + (end_scale - start_scale) * progress
        cx = x_start + (x_end - x_start) * progress
        cy = y_start + (y_end - y_start) * progress

        frame = get_frame(t)
        h, w = frame.shape[:2]

        # Crop size based on zoom scale
        crop_w = int(w / scale)
        crop_h = int(h / scale)
        crop_w = max(2, min(crop_w, w))
        crop_h = max(2, min(crop_h, h))

        # Center of crop based on anchor drift
        center_x = int(w * cx)
        center_y = int(h * cy)

        x1 = max(0, center_x - crop_w // 2)
        y1 = max(0, center_y - crop_h // 2)

        # Ensure we don't go out of bounds
        x1 = min(x1, w - crop_w)
        y1 = min(y1, h - crop_h)

        cropped = frame[y1:y1 + crop_h, x1:x1 + crop_w]

        # Resize back to original dimensions
        from PIL import Image
        img = Image.fromarray(cropped)
        img = img.resize((w, h), Image.LANCZOS)
        return np.array(img)

    anchor_desc = f'{x_anchor}-{y_anchor}'
    logger.info(f'Effect: ken-burns {zoom_direction} from {anchor_desc} '
                f'({start_scale}→{end_scale}, {duration}s)')
    return clip.fl(ken_burns_filter)
