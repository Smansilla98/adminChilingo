# Servicio transcriptor de percusión (CPU). Railway: servicio aparte con Root Directory = transcriptor/.
# Base: Python 3.12 (la misma versión con la que se corren los tests y se fijaron las dependencias).
FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    NUMBA_CACHE_DIR=/tmp/numba

# ffmpeg: leer MP3/M4A/OGG; libsndfile: WAV/FLAC; git: solo lo necesita beat_this con CON_ML=1.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg libsndfile1 git \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv

# Dependencias base, todas fijadas (requirements.txt). Incluye onnxruntime para el clasificador propio.
COPY requirements.txt .
RUN pip install -r requirements.txt

# Opcional (imagen mucho más pesada, ≈1 GB): Beat This!, Demucs y torch.
# Se activa con la variable de build CON_ML=1. El clasificador entrenado NO lo necesita.
ARG CON_ML=0
COPY requirements-ml.txt .
RUN if [ "$CON_ML" = "1" ]; then pip install -r requirements-ml.txt; fi

COPY app ./app
# modelos/clasificador.onnx es opcional: si no está, el servicio usa las reglas de librosa.
COPY modelos ./modelos

RUN useradd --create-home transcriptor
USER transcriptor

EXPOSE 8080
CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8080} --workers 1"]
