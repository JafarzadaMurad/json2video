FROM python:3.11-slim

# Install FFmpeg and system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    imagemagick \
    libsm6 \
    libxext6 \
    libfontconfig1 \
    fonts-dejavu-core \
    fonts-liberation \
    wget \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Montserrat font from Google Fonts
RUN mkdir -p /usr/share/fonts/truetype/montserrat && \
    wget -q -O /tmp/montserrat.zip "https://fonts.google.com/download?family=Montserrat" && \
    unzip -o /tmp/montserrat.zip -d /tmp/montserrat && \
    cp /tmp/montserrat/static/*.ttf /usr/share/fonts/truetype/montserrat/ 2>/dev/null || true && \
    cp /tmp/montserrat/*.ttf /usr/share/fonts/truetype/montserrat/ 2>/dev/null || true && \
    fc-cache -f -v && \
    rm -rf /tmp/montserrat /tmp/montserrat.zip

# Fix ImageMagick security policy (allows MoviePy TextClip to work)
RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
    sed -i 's/<policy domain="path" rights="none" pattern="@\*"/<policy domain="path" rights="read|write" pattern="@*"/' /etc/ImageMagick-6/policy.xml; \
    fi && \
    if [ -f /etc/ImageMagick-7/policy.xml ]; then \
    sed -i 's/<policy domain="path" rights="none" pattern="@\*"/<policy domain="path" rights="read|write" pattern="@*"/' /etc/ImageMagick-7/policy.xml; \
    fi

WORKDIR /app

# Copy requirements first for better caching
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application
COPY . .

# Create temp directory for renders
RUN mkdir -p /tmp/renders /app/storage/renders

CMD ["python", "-m", "app.worker"]
