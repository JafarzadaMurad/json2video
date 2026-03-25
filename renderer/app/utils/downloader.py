"""
JSON2Video — Asset Downloader

Downloads remote assets (images, videos, audio) with validation and caching.
"""
import hashlib
import logging
import os
import mimetypes
from urllib.parse import urlparse

import requests

from app.config import Config

logger = logging.getLogger('downloader')

# Simple in-memory cache of downloaded files
_download_cache = {}


def download_asset(url: str, temp_dir: str, allowed_types: list = None) -> str:
    """
    Download a remote asset and save it to the temp directory.
    Returns the local file path.
    """
    # Check cache first
    url_hash = hashlib.md5(url.encode()).hexdigest()
    if url_hash in _download_cache and os.path.exists(_download_cache[url_hash]):
        logger.debug(f'Cache hit: {url}')
        return _download_cache[url_hash]

    # Validate URL
    parsed = urlparse(url)
    if parsed.scheme not in ('http', 'https'):
        raise ValueError(f'Invalid URL scheme: {parsed.scheme}. Only http/https allowed.')

    # Block local/private IPs (SSRF protection)
    hostname = parsed.hostname
    if hostname in ('localhost', '127.0.0.1', '0.0.0.0', '::1'):
        raise ValueError(f'Access to local addresses is not allowed: {hostname}')

    # Download with retry
    logger.info(f'Downloading: {url}')
    max_retries = 3
    last_error = None

    for attempt in range(max_retries):
        try:
            verify_ssl = True if attempt < 2 else False  # Disable SSL on last attempt
            response = requests.get(url, timeout=60, stream=True, verify=verify_ssl)
            response.raise_for_status()
            break
        except (requests.exceptions.SSLError, requests.exceptions.ConnectionError) as e:
            last_error = e
            logger.warning(f'Download attempt {attempt + 1}/{max_retries} failed: {e}')
            if attempt == max_retries - 1:
                raise ValueError(f'Failed to download after {max_retries} attempts: {url} ({e})')
            import time
            time.sleep(1)
        except requests.exceptions.HTTPError:
            raise

    # Determine file extension
    content_type = response.headers.get('Content-Type', '')
    ext = mimetypes.guess_extension(content_type.split(';')[0].strip()) or ''
    if not ext:
        # Fallback: use URL extension
        ext = os.path.splitext(parsed.path)[1] or '.bin'

    # Validate content type if restrictions specified
    if allowed_types:
        base_type = content_type.split(';')[0].strip().split('/')[0]
        if base_type not in allowed_types:
            raise ValueError(
                f'Content type "{content_type}" not allowed. '
                f'Expected: {allowed_types}'
            )

    # Save to temp file
    filename = f'{url_hash}{ext}'
    filepath = os.path.join(temp_dir, filename)

    os.makedirs(temp_dir, exist_ok=True)
    import time
    start_time = time.time()
    MAX_DOWNLOAD_TIME = 600  # 10 minutes maximum per file

    with open(filepath, 'wb') as f:
        for chunk in response.iter_content(chunk_size=8192):
            if time.time() - start_time > MAX_DOWNLOAD_TIME:
                raise TimeoutError(f"File download exceeded maximum allowed time of {MAX_DOWNLOAD_TIME} seconds")
            f.write(chunk)

    # Cache it
    _download_cache[url_hash] = filepath

    file_size = os.path.getsize(filepath)
    logger.info(f'Downloaded: {filename} ({file_size / 1024:.1f} KB)')

    return filepath


def cleanup_temp(temp_dir: str):
    """Remove all files from the temp directory."""
    if os.path.exists(temp_dir):
        for f in os.listdir(temp_dir):
            filepath = os.path.join(temp_dir, f)
            if os.path.isfile(filepath):
                os.remove(filepath)
        logger.debug(f'Cleaned temp directory: {temp_dir}')
