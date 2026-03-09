"""
JSON2Video — Progress Reporter

Reports render progress back to MySQL so the API can serve it.
"""
import logging
import time

logger = logging.getLogger('progress')


class ProgressReporter:
    """Reports render progress to the database."""

    def __init__(self, db, job_id: str, total_scenes: int):
        self.db = db
        self.job_id = job_id
        self.total_scenes = total_scenes
        self.current_scene = 0
        self._last_report_time = 0

    def update(self, scene_index: int, phase: str = 'rendering'):
        """Update progress based on scene completion."""
        self.current_scene = scene_index + 1
        progress = int((self.current_scene / self.total_scenes) * 90)  # Reserve 10% for encoding
        progress = min(progress, 90)

        # Throttle DB updates to at most once per second
        now = time.time()
        if now - self._last_report_time < 1.0:
            return

        self._last_report_time = now
        self._write_progress(progress)
        logger.debug(f'Job {self.job_id}: {progress}% (scene {self.current_scene}/{self.total_scenes}, {phase})')

    def encoding(self):
        """Mark that we're in the final encoding phase."""
        self._write_progress(90)
        logger.debug(f'Job {self.job_id}: 90% (encoding)')

    def uploading(self):
        """Mark that we're uploading the result."""
        self._write_progress(95)
        logger.debug(f'Job {self.job_id}: 95% (uploading)')

    def _write_progress(self, progress: int):
        """Write progress to database."""
        try:
            cursor = self.db.cursor()
            cursor.execute(
                'UPDATE render_jobs SET progress = %s WHERE id = %s',
                (progress, self.job_id)
            )
            cursor.close()
        except Exception as e:
            logger.warning(f'Failed to update progress: {e}')
