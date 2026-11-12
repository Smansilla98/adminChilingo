# Transcriptor de percusión · La Chilinga

Servicio aparte (Python, FastAPI, CPU) que escucha una grabación del toque y devuelve
pulsos, primer tiempo de cada compás y golpes por instrumento. Laravel convierte eso en
un **borrador** de partitura (nunca publica solo): ver `app/Support/PartituraDesdeGolpes.php`.

Klangio no transcribe percusión por API, por eso esto es propio.

## Cómo funciona

1. En el editor alguien sube la grabación y toca **Transcribir desde audio**.
2. Laravel crea un pedido (`partitura_transcripciones`) y el job `TranscribirAudio` llama
   `POST /transcribir` con un enlace firmado y temporal para bajar el audio.
3. El servicio responde 202, procesa en segundo plano y avisa a `callback_url`
   (`/api/transcriptor/callback`) con el progreso y el resultado.
4. Laravel cuantiza por pulsos y guarda el borrador con autor «Transcripción automática».
   Los compases con golpes de poca confianza quedan marcados «Revisar: …».

### Contrato

```
POST /transcribir                     cabecera X-Transcriptor-Firma: sha256=<hex HMAC-SHA256(cuerpo, TRANSCRIPTOR_SECRETO)>
{ "audio_url": "...", "callback_url": "...", "job_id": "12", "separar_fuentes": false }   → 202

Aviso (firmado igual):
{ "job_id": "12", "estado": "procesando", "progreso": 30 }
{ "job_id": "12", "estado": "listo", "progreso": 100,
  "resultado": { "tempo": 98.4, "ts": {"num": 4, "den": 4}, "beats": [..seg..], "downbeats": [..seg..],
                 "golpes": [ { "t": 1.234, "inst": "surdo_grave", "golpe": "nota", "vel": 104, "conf": 0.82 } ],
                 "metodo": { "pulsos": "librosa", "golpes": "reglas", "separacion": false } } }
{ "job_id": "12", "estado": "error", "error": "..." }

GET /salud → { ok, configurado, clasificador }
```

### Métodos (y licencias)

| Paso | Preferido (si está instalado) | Respaldo (siempre) |
|---|---|---|
| Pulsos y primer tiempo | Beat This! (CPJKU, MIT) | librosa `beat_track` + tempo por autocorrelación (70–150 BPM) + fase por graves (el surdo marca el 1) |
| Golpes | Clasificador propio `modelos/clasificador.onnx` | `onset_detect` + bandas: grave→surdos (por frecuencia del pico), metal afinado→agogó, ruido de banda media→redoblante/palmas, membrana media/aguda→repique/timbal |
| Separar voces | Demucs (MIT) con `separar_fuentes` | sin separar |

**No** se usan modelos de madmom (CC BY-NC-SA). ADTOF no está integrado: antes de sumarlo
hay que revisar la licencia de sus pesos. **YouTube no se descarga** (términos de uso):
solo subida de archivo.

## Correr local

```bash
python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements.txt pytest==8.3.4
pytest -q                                    # audio sintético a tempo conocido
TRANSCRIPTOR_SECRETO=algo uvicorn app.main:app --port 8080
```

Laravel (`.env`): `TRANSCRIPTOR_URL=http://localhost:8080` y el mismo `TRANSCRIPTOR_SECRETO`.
Sin `TRANSCRIPTOR_URL` el botón aparece apagado con un mensaje claro.

## Desplegar en Railway

1. En el proyecto de Railway: **New → GitHub Repo** (el mismo repo) → servicio nuevo `transcriptor`.
2. Settings → **Root Directory** = `transcriptor`; Builder = Dockerfile (lo detecta solo).
   Para la imagen con Beat This!/ONNX/Demucs: variable de build `CON_ML=1` (≈1 GB más; necesita ≥2 GB de RAM).
3. Variables del servicio transcriptor:
   - `TRANSCRIPTOR_SECRETO` = una clave larga al azar (`openssl rand -hex 32`).
   - `TRANSCRIPTOR_HOSTS_PERMITIDOS` = el dominio público de Laravel (p. ej. `admin.lachilinga.com.ar`),
     para que el servicio solo baje audio y avise a ese host.
4. Networking: generar dominio público **o** usar la red privada (`transcriptor.railway.internal:8080`).
   Con red privada no hace falta exponerlo.
5. Variables del servicio Laravel: `TRANSCRIPTOR_URL` (p. ej. `http://transcriptor.railway.internal:8080`),
   `TRANSCRIPTOR_SECRETO` (el mismo) y, opcional, `TRANSCRIPTOR_CONFIANZA_MINIMA` (0.5),
   `TRANSCRIPTOR_SEPARAR_FUENTES` (`false`).
6. Laravel necesita un **worker de colas** (`php artisan queue:work`) para el job `TranscribirAudio`
   (lo agrega el equipo de infraestructura). El callback llega a `/api/transcriptor/callback`,
   que tiene que ser alcanzable desde el transcriptor (dominio público de Laravel o red privada).
7. Healthcheck: `GET /salud`.

## Entrenar el clasificador propio

Ver `entrenamiento/README.md`: genera audio sintético desde las partituras del repo con las
muestras WAV de `public/sounds/perc`, entrena una CNN chica sobre mel de 150 ms en CPU y
exporta `modelos/clasificador.onnx`. Las correcciones humanas se exportan desde Laravel con
`php artisan partituras:dataset-correcciones`.
