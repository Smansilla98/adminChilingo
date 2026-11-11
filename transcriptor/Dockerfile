# Servicio transcriptor de percusión (CPU). Railway: servicio aparte con Root Directory = transcriptor/.
FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    NUMBA_CACHE_DIR=/tmp/numba

# ffmpeg: leer MP3/M4A/OGG; libsndfile: WAV/FLAC.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg libsndfile1 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv
COPY requirements.txt .
RUN pip install -r requirements.txt

# Opcional (imagen mucho más pesada): Beat This!, ONNX del clasificador propio y Demucs.
ARG CON_ML=0
COPY requirements-ml.txt .
RUN if [ "$CON_ML" = "1" ]; then pip install -r requirements-ml.txt; fi

COPY app ./app
COPY modelos ./modelos

RUN useradd --create-home transcriptor
USER transcriptor

EXPOSE 8080
CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8080} --workers 1"]
