"""
JSON2Video — Render Engine

Main orchestrator that processes a JSON payload into an MP4 video.
Composes scenes with elements, applies transitions, and produces final output.
"""
import logging
import os
import time
import uuid

import numpy as np
from moviepy.editor import (
    CompositeVideoClip,
    CompositeAudioClip,
    ColorClip,
    concatenate_videoclips,
    AudioFileClip,
    vfx,
)

from app.config import Config
from app.elements.image import ImageElement
from app.elements.text import TextElement
from app.elements.audio import AudioElement
from app.elements.video import VideoElement
from app.elements.subtitles import SubtitlesElement
from app.utils.downloader import cleanup_temp
from app.utils.progress_reporter import ProgressReporter

logger = logging.getLogger('engine')

# Element type → handler class mapping
ELEMENT_HANDLERS = {
    'image': ImageElement,
    'text': TextElement,
    'audio': AudioElement,
    'video': VideoElement,
    'subtitles': SubtitlesElement,
}


def hex_to_rgb(hex_color: str) -> tuple:
    """Convert hex color string to RGB tuple."""
    hex_color = hex_color.lstrip('#')
    if len(hex_color) == 3:
        hex_color = ''.join([c * 2 for c in hex_color])
    return tuple(int(hex_color[i:i+2], 16) for i in (0, 2, 4))


# ─── Easing Functions ─────────────────────────────

def ease_linear(t):
    return t

def ease_in(t):
    return t * t

def ease_out(t):
    return 1 - (1 - t) * (1 - t)

def ease_in_out(t):
    return 3 * t * t - 2 * t * t * t

EASING_FUNCTIONS = {
    'linear': ease_linear,
    'ease-in': ease_in,
    'ease-out': ease_out,
    'ease-in-out': ease_in_out,
}


class RenderEngine:
    """
    Main render engine. Takes a JSON payload and produces an MP4 video.
    Supports custom resolutions, scene transitions, and element animations.
    """

    def __init__(self, job_id: str, payload: dict, resolution: str,
                 quality: str, db):
        self.job_id = job_id
        self.payload = payload
        self.resolution_name = resolution
        self.quality = quality
        self.db = db

        # Resolve resolution to (width, height)
        if resolution == 'custom':
            self.resolution = (
                int(payload.get('width', 1920)),
                int(payload.get('height', 1080)),
            )
        else:
            self.resolution = Config.RESOLUTIONS.get(resolution, (1920, 1080))

        self.fps = int(payload.get('fps', 30))
        self.crf = Config.QUALITY_CRF.get(quality, 18)

        # Temp directory for this job
        self.temp_dir = os.path.join(Config.TEMP_DIR, job_id)
        os.makedirs(self.temp_dir, exist_ok=True)

        # Parse scenes
        self.scenes = payload.get('scenes', [])

        # Progress reporter
        self.progress = ProgressReporter(db, job_id, len(self.scenes))

    def render(self) -> dict:
        """Execute the full render pipeline."""
        start_time = time.time()
        logger.info(f'Starting render: {self.job_id} '
                     f'({self.resolution[0]}x{self.resolution[1]}, '
                     f'quality={self.quality}, fps={self.fps}, scenes={len(self.scenes)})')

        try:
            # Step 1: Render each scene
            scene_clips = []
            for i, scene_data in enumerate(self.scenes):
                self.progress.update(i, phase='rendering scene')
                clip = self._render_scene(i, scene_data)
                scene_clips.append(clip)
                logger.info(f'Scene {i+1}/{len(self.scenes)} rendered')

            # Step 2: Apply transitions between scenes
            if len(scene_clips) > 1:
                logger.info('Applying transitions...')
                scene_clips = self._apply_transitions(scene_clips)

            # Step 3: Concatenate scenes
            logger.info('Concatenating scenes...')
            if len(scene_clips) == 1:
                final_clip = scene_clips[0]
            else:
                final_clip = concatenate_videoclips(scene_clips, method='compose')

            # Step 4: Encode and write to file
            self.progress.encoding()
            output_filename = f'{self.job_id}.mp4'
            output_path = os.path.join(Config.STORAGE_PATH, 'videos', output_filename)
            os.makedirs(os.path.dirname(output_path), exist_ok=True)

            logger.info(f'Encoding final video: {output_path}')
            final_clip.write_videofile(
                output_path,
                fps=self.fps,
                codec='libx264',
                audio_codec='aac',
                preset='medium',
                ffmpeg_params=['-crf', str(self.crf)],
                logger=None,
            )

            # Step 5: Get file info
            file_size = os.path.getsize(output_path)
            duration = final_clip.duration
            output_url = f'{Config.STORAGE_URL}/videos/{output_filename}'

            # Step 6: Generate thumbnail
            self.progress.uploading()
            thumbnail_path = self._generate_thumbnail(final_clip, output_filename)

            # Cleanup
            final_clip.close()
            for clip in scene_clips:
                try:
                    clip.close()
                except Exception:
                    pass
            cleanup_temp(self.temp_dir)

            elapsed = time.time() - start_time
            logger.info(f'Render complete: {duration:.1f}s video, '
                         f'{file_size / (1024*1024):.1f} MB, '
                         f'took {elapsed:.1f}s')

            return {
                'output_path': output_path,
                'output_url': output_url,
                'thumbnail_path': thumbnail_path,
                'duration_seconds': round(float(duration), 2),
                'file_size_bytes': int(file_size),
            }

        except Exception:
            cleanup_temp(self.temp_dir)
            raise

    # ─── Scene Rendering ──────────────────────────

    def _render_scene(self, index: int, scene_data: dict) -> CompositeVideoClip:
        """Render a single scene with all its elements."""
        duration = scene_data.get('duration', 5)
        elements = scene_data.get('elements', [])
        comment = scene_data.get('comment', f'Scene {index + 1}')
        bg_color_hex = scene_data.get('background', '#000000')

        logger.info(f'Rendering scene: "{comment}" '
                     f'(duration={duration}s, elements={len(elements)})')

        # Create background
        bg_color = hex_to_rgb(bg_color_hex)
        bg = ColorClip(
            size=self.resolution,
            color=bg_color,
        ).set_duration(duration)

        # Process elements
        visual_clips = [bg]
        audio_clips = []
        element_errors = []

        for elem_data in elements:
            elem_type = elem_data.get('type')

            if elem_type not in ELEMENT_HANDLERS:
                logger.warning(f'Unknown element type: {elem_type}, skipping')
                continue

            handler_class = ELEMENT_HANDLERS[elem_type]
            handler = handler_class(
                element_data=elem_data,
                scene_duration=duration,
                resolution=self.resolution,
            )

            try:
                result = handler.render(self.temp_dir)

                # Subtitles (SRT) may return a list of clips
                rendered_clips = result if isinstance(result, list) else [result]

                for clip in rendered_clips:
                    # Apply animation if specified (not for audio or subtitle sub-clips from SRT)
                    animation = elem_data.get('animation')
                    if animation and elem_type not in ('audio',) and not isinstance(result, list):
                        clip = self._apply_animation(clip, animation, duration)

                    if elem_type == 'audio':
                        audio_clips.append(clip)
                    elif elem_type == 'video':
                        # Only extract audio if not muted
                        is_muted = elem_data.get('mute', False)
                        if not is_muted and clip.audio:
                            audio_clips.append(clip.audio)
                        # Always strip audio from visual layer
                        visual_clips.append(clip.without_audio())
                    else:
                        visual_clips.append(clip)

            except Exception as e:
                error_msg = f'Scene #{index+1}, element {elem_type}: {e}'
                logger.error(f'Failed to render {elem_type} element: {e}')
                element_errors.append(error_msg)
                continue

        # If any elements failed, raise an error with all messages
        if element_errors:
            raise RuntimeError(
                f'{len(element_errors)} element(s) failed to render: ' +
                '; '.join(element_errors)
            )

        # Compose the scene
        scene_clip = CompositeVideoClip(visual_clips, size=self.resolution)
        scene_clip = scene_clip.set_duration(duration)

        # Mix audio
        if audio_clips:
            try:
                composite_audio = CompositeAudioClip(audio_clips)
                scene_clip = scene_clip.set_audio(composite_audio)
            except Exception as e:
                logger.warning(f'Failed to mix audio for scene: {e}')

        return scene_clip

    # ─── Transitions ──────────────────────────────

    def _apply_transitions(self, scene_clips: list) -> list:
        """Apply transitions between scenes based on scene data."""
        result = [scene_clips[0]]

        for i in range(1, len(scene_clips)):
            scene_data = self.scenes[i]
            transition = scene_data.get('transition')

            if transition:
                trans_type = transition.get('type', 'fade')
                trans_duration = float(transition.get('duration', 0.5))

                try:
                    prev_clip = result[-1]
                    curr_clip = scene_clips[i]

                    if trans_type == 'fade':
                        # Crossfade: fade out previous, fade in current
                        prev_clip = prev_clip.crossfadeout(trans_duration)
                        curr_clip = curr_clip.crossfadein(trans_duration)
                        curr_clip = curr_clip.set_start(prev_clip.duration - trans_duration)
                        result[-1] = prev_clip
                        result.append(curr_clip)

                    elif trans_type in ('slide-left', 'slide-right', 'slide-up', 'slide-down'):
                        curr_clip = self._slide_transition(
                            prev_clip, curr_clip, trans_type, trans_duration
                        )
                        result.append(curr_clip)

                    elif trans_type in ('zoom-in', 'zoom-out'):
                        curr_clip = self._zoom_transition(
                            curr_clip, trans_type, trans_duration
                        )
                        result.append(curr_clip)

                    elif trans_type == 'dissolve':
                        prev_clip = prev_clip.crossfadeout(trans_duration)
                        curr_clip = curr_clip.crossfadein(trans_duration)
                        curr_clip = curr_clip.set_start(prev_clip.duration - trans_duration)
                        result[-1] = prev_clip
                        result.append(curr_clip)

                    elif trans_type == 'wipe':
                        result.append(curr_clip)

                    else:
                        result.append(curr_clip)

                    logger.info(f'Transition: {trans_type} ({trans_duration}s)')

                except Exception as e:
                    logger.warning(f'Failed to apply {trans_type} transition: {e}')
                    result.append(scene_clips[i])
            else:
                result.append(scene_clips[i])

        return result

    def _slide_transition(self, prev_clip, curr_clip, direction: str,
                          duration: float):
        """Create a slide transition effect."""
        w, h = self.resolution

        if direction == 'slide-left':
            curr_clip = curr_clip.set_position(
                lambda t: (max(0, w - (w * min(t / duration, 1))), 0)
            )
        elif direction == 'slide-right':
            curr_clip = curr_clip.set_position(
                lambda t: (min(0, -w + (w * min(t / duration, 1))), 0)
            )
        elif direction == 'slide-up':
            curr_clip = curr_clip.set_position(
                lambda t: (0, max(0, h - (h * min(t / duration, 1))))
            )
        elif direction == 'slide-down':
            curr_clip = curr_clip.set_position(
                lambda t: (0, min(0, -h + (h * min(t / duration, 1))))
            )

        return curr_clip

    def _zoom_transition(self, clip, zoom_type: str, duration: float):
        """Create a zoom transition effect."""
        if zoom_type == 'zoom-in':
            clip = clip.fx(vfx.resize, lambda t: 0.5 + 0.5 * min(t / duration, 1))
        elif zoom_type == 'zoom-out':
            clip = clip.fx(vfx.resize, lambda t: 1.5 - 0.5 * min(t / duration, 1))

        return clip

    # ─── Element Animations ───────────────────────

    def _apply_animation(self, clip, animation: dict, scene_duration: float):
        """Apply enter/exit animation to an element clip."""
        anim_type = animation.get('type', 'fade-in')
        anim_duration = float(animation.get('duration', 0.5))
        easing_name = animation.get('easing', 'ease-out')

        try:
            if anim_type == 'fade-in':
                clip = clip.crossfadein(anim_duration)

            elif anim_type == 'fade-out':
                clip = clip.crossfadeout(anim_duration)

            elif anim_type.startswith('slide-in-'):
                clip = self._slide_in_animation(clip, anim_type, anim_duration, easing_name)

            elif anim_type == 'zoom-in':
                clip = clip.fx(vfx.resize, lambda t: min(1.0, 0.3 + 0.7 * min(t / anim_duration, 1)))

            elif anim_type == 'zoom-out':
                clip = clip.fx(vfx.resize, lambda t: max(1.0, 1.7 - 0.7 * min(t / anim_duration, 1)))

            elif anim_type == 'bounce':
                original_pos = clip.pos if hasattr(clip, 'pos') else lambda t: (0, 0)
                px, py = clip.pos(0) if callable(clip.pos) else clip.pos

                def bounce_pos(t):
                    if t < anim_duration:
                        progress = t / anim_duration
                        bounce = abs(np.sin(progress * np.pi * 3)) * (1 - progress) * 30
                        return (px, py - bounce)
                    return (px, py)

                clip = clip.set_position(bounce_pos)

            logger.debug(f'Animation applied: {anim_type} ({anim_duration}s)')

        except Exception as e:
            logger.warning(f'Failed to apply animation {anim_type}: {e}')

        return clip

    def _slide_in_animation(self, clip, anim_type: str, duration: float,
                             easing_name: str):
        """Slide in animation from a direction."""
        w, h = self.resolution
        easing = EASING_FUNCTIONS.get(easing_name, ease_out)

        # Get target position
        px, py = clip.pos(0) if callable(clip.pos) else clip.pos

        if anim_type == 'slide-in-left':
            clip = clip.set_position(
                lambda t: (px - w * (1 - easing(min(t / duration, 1))), py)
            )
        elif anim_type == 'slide-in-right':
            clip = clip.set_position(
                lambda t: (px + w * (1 - easing(min(t / duration, 1))), py)
            )
        elif anim_type == 'slide-in-top':
            clip = clip.set_position(
                lambda t: (px, py - h * (1 - easing(min(t / duration, 1))))
            )
        elif anim_type == 'slide-in-bottom':
            clip = clip.set_position(
                lambda t: (px, py + h * (1 - easing(min(t / duration, 1))))
            )

        return clip

    # ─── Thumbnail Generation ─────────────────────

    def _generate_thumbnail(self, clip, output_filename: str) -> str:
        """Generate a thumbnail from the first frame of the video."""
        try:
            from PIL import Image

            thumb_filename = output_filename.replace('.mp4', '_thumb.jpg')
            thumb_path = os.path.join(Config.STORAGE_PATH, 'thumbnails', thumb_filename)
            os.makedirs(os.path.dirname(thumb_path), exist_ok=True)

            # Get frame at 1 second (or 10% of the video if very short)
            t = min(1.0, clip.duration * 0.1)
            temp_frame = os.path.join(self.temp_dir, 'thumb_temp.png')
            clip.save_frame(temp_frame, t=t)

            # Convert RGBA → RGB for JPEG
            img = Image.open(temp_frame)
            if img.mode in ('RGBA', 'LA'):
                background = Image.new('RGB', img.size, (0, 0, 0))
                background.paste(img, mask=img.split()[-1])
                img = background
            elif img.mode != 'RGB':
                img = img.convert('RGB')
            img.save(thumb_path, 'JPEG', quality=85)

            logger.info(f'Thumbnail generated: {thumb_path}')
            return thumb_path

        except Exception as e:
            logger.warning(f'Failed to generate thumbnail: {e}')
            return None
