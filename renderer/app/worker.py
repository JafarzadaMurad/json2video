"""
JSON2Video Render Engine — Redis Queue Worker

Listens to the Redis 'render:jobs' queue for new render jobs,
processes them using the render engine, and updates job status in MySQL.
"""
import json
import logging
import signal
import sys
import time
import traceback

# Fix Pillow 10.x compatibility (ANTIALIAS removed, MoviePy still uses it)
import PIL.Image
if not hasattr(PIL.Image, 'ANTIALIAS'):
    PIL.Image.ANTIALIAS = PIL.Image.LANCZOS

import redis
import mysql.connector

from app.config import Config
from app.renderer.engine import RenderEngine

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
)
logger = logging.getLogger('worker')

# Graceful shutdown
running = True


def signal_handler(sig, frame):
    global running
    logger.info('Received shutdown signal. Finishing current job...')
    running = False


signal.signal(signal.SIGINT, signal_handler)
signal.signal(signal.SIGTERM, signal_handler)


def get_db_connection():
    """Create a new MySQL connection."""
    return mysql.connector.connect(
        host=Config.DB_HOST,
        port=Config.DB_PORT,
        database=Config.DB_DATABASE,
        user=Config.DB_USERNAME,
        password=Config.DB_PASSWORD,
        autocommit=True,
    )


def update_job_status(db, job_id: str, status: str, **kwargs):
    """Update a render job's status and additional fields in the database."""
    cursor = db.cursor()

    set_clauses = ['status = %s']
    values = [status]

    for key, value in kwargs.items():
        set_clauses.append(f'{key} = %s')
        # Convert numpy types to native Python types
        if hasattr(value, 'item'):
            value = value.item()
        elif isinstance(value, float) and not isinstance(value, (int, str)):
            value = float(value)
        values.append(value)

    values.append(job_id)

    query = f"UPDATE render_jobs SET {', '.join(set_clauses)} WHERE id = %s"
    cursor.execute(query, values)
    cursor.close()


def process_job(job_data: dict, db):
    """Process a single render job."""
    job_id = job_data['job_id']
    logger.info(f'Processing job: {job_id}')

    # Mark as processing
    update_job_status(
        db, job_id, 'processing',
        worker_id=Config.WORKER_ID,
        started_at=time.strftime('%Y-%m-%d %H:%M:%S'),
        progress=0,
    )

    try:
        # Initialize the render engine
        engine = RenderEngine(
            job_id=job_id,
            payload=job_data['payload'],
            resolution=job_data.get('resolution', 'full-hd'),
            quality=job_data.get('quality', 'high'),
            db=db,
        )

        # Run the render
        result = engine.render()

        # Mark as done
        update_job_status(
            db, job_id, 'done',
            progress=100,
            output_path=result['output_path'],
            output_url=result['output_url'],
            thumbnail_path=result.get('thumbnail_path'),
            duration_seconds=result['duration_seconds'],
            file_size_bytes=result['file_size_bytes'],
            completed_at=time.strftime('%Y-%m-%d %H:%M:%S'),
        )

        # Send webhook notification
        send_webhook(db, job_id, 'done', job_data, result=result)

        logger.info(f'Job {job_id} completed successfully. Output: {result["output_url"]}')

    except Exception as e:
        error_msg = str(e)
        error_trace = traceback.format_exc()
        logger.error(f'Job {job_id} failed: {error_msg}\n{error_trace}')

        update_job_status(
            db, job_id, 'failed',
            error_message=error_msg[:2000],
            error_code='RENDER_ERROR',
            completed_at=time.strftime('%Y-%m-%d %H:%M:%S'),
        )

        # Send webhook notification for failure
        send_webhook(db, job_id, 'failed', job_data, error=error_msg)


def send_webhook(db, job_id: str, status: str, job_data: dict, **kwargs):
    """Send webhook notification if configured."""
    import requests as http_requests

    try:
        user_id = job_data.get('user_id')
        webhook_url = job_data.get('webhook_url')

        # Check user's webhook config if no direct URL
        if not webhook_url and user_id:
            cursor = db.cursor(dictionary=True)
            cursor.execute(
                'SELECT url FROM webhook_configs WHERE user_id = %s AND is_active = 1',
                (user_id,)
            )
            row = cursor.fetchone()
            cursor.close()
            if row:
                webhook_url = row['url']

        if not webhook_url:
            return

        payload = {
            'event': f'render.{status}',
            'job_id': job_id,
            'status': status,
            'timestamp': time.strftime('%Y-%m-%dT%H:%M:%SZ'),
        }

        if status == 'done' and 'result' in kwargs:
            result = kwargs['result']
            payload['url'] = result.get('output_url')
            payload['duration'] = result.get('duration_seconds')
            payload['size_bytes'] = result.get('file_size_bytes')

        if status == 'failed' and 'error' in kwargs:
            payload['error'] = kwargs['error'][:500]

        http_requests.post(webhook_url, json=payload, timeout=10)
        logger.info(f'Webhook sent to {webhook_url} for job {job_id}')

    except Exception as e:
        logger.warning(f'Webhook failed for job {job_id}: {e}')


def main():
    """Main worker loop — listen to Redis queue and process jobs."""
    logger.info(f'🚀 JSON2Video Render Worker starting (ID: {Config.WORKER_ID})')
    logger.info(f'   Redis: {Config.REDIS_HOST}:{Config.REDIS_PORT}')
    logger.info(f'   MySQL: {Config.DB_HOST}:{Config.DB_PORT}/{Config.DB_DATABASE}')
    logger.info(f'   Storage: {Config.STORAGE_PATH}')

    # Connect to Redis
    r = redis.Redis(
        host=Config.REDIS_HOST,
        port=Config.REDIS_PORT,
        decode_responses=True,
    )

    # Wait for Redis to be ready
    while running:
        try:
            r.ping()
            logger.info('Connected to Redis ✅')
            break
        except redis.ConnectionError:
            logger.warning('Waiting for Redis...')
            time.sleep(2)

    # Connect to MySQL
    db = None
    while running:
        try:
            db = get_db_connection()
            logger.info('Connected to MySQL ✅')
            break
        except mysql.connector.Error as e:
            logger.warning(f'Waiting for MySQL... ({e})')
            time.sleep(3)

    if not running or not db:
        return

    logger.info(f'Listening for jobs on queue: {Config.REDIS_QUEUE}')

    while running:
        try:
            # BLPOP blocks until a message is available (timeout 5s)
            result = r.blpop(Config.REDIS_QUEUE, timeout=5)

            if result is None:
                continue  # Timeout, loop again

            _, raw_message = result
            job_data = json.loads(raw_message)

            # Ensure DB connection is alive
            if not db.is_connected():
                db = get_db_connection()

            process_job(job_data, db)

        except redis.ConnectionError:
            logger.error('Lost Redis connection. Reconnecting...')
            time.sleep(2)
            try:
                r = redis.Redis(
                    host=Config.REDIS_HOST,
                    port=Config.REDIS_PORT,
                    decode_responses=True,
                )
            except Exception:
                pass

        except Exception as e:
            logger.error(f'Unexpected error: {e}\n{traceback.format_exc()}')
            time.sleep(1)

    logger.info('Worker shutting down.')
    if db and db.is_connected():
        db.close()


if __name__ == '__main__':
    main()
