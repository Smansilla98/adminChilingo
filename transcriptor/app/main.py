"""Servicio transcriptor de percusión para La Chilinga (FastAPI).

POST /transcribir  {audio_url, callback_url, job_id, separar_fuentes?}  → 202
  Cuerpo firmado: cabecera X-Transcriptor-Firma = "sha256=" + HMAC-SHA256(cuerpo, TRANSCRIPTOR_SECRETO).
  Procesa en segundo plano y avisa a callback_url (firmado igual) con:
    {job_id, estado: "procesando", progreso}            (avances)
    {job_id, estado: "listo", resultado: {tempo, ts, beats, downbeats, golpes:[{t, inst, golpe, vel, conf}]}}
    {job_id, estado: "error", error}
GET /salud → estado y qué métodos están disponibles.
"""
from __future__ import annotations

import hashlib
import hmac
import json
import logging
import os
import tempfile
from urllib.parse import urlparse

import httpx
from fastapi import BackgroundTasks, FastAPI, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from . import analisis

log = logging.getLogger("transcriptor")
logging.basicConfig(level=logging.INFO)

MAX_BYTES = 60 * 1024 * 1024
CABECERA = "X-Transcriptor-Firma"

app = FastAPI(title="Transcriptor de percusión · La Chilinga", version="1.0.0")


def secreto() -> str:
    return os.environ.get("TRANSCRIPTOR_SECRETO", "")


def firmar(cuerpo: bytes) -> str:
    return "sha256=" + hmac.new(secreto().encode(), cuerpo, hashlib.sha256).hexdigest()


def firma_valida(cuerpo: bytes, firma: str | None) -> bool:
    return bool(secreto()) and bool(firma) and hmac.compare_digest(firmar(cuerpo), firma or "")


def host_permitido(url: str) -> bool:
    """Evita que el servicio se use para pedir URLs arbitrarias (SSRF)."""
    u = urlparse(url)
    if u.scheme not in ("https", "http"):
        return False
    permitidos = [h.strip().lower() for h in os.environ.get("TRANSCRIPTOR_HOSTS_PERMITIDOS", "").split(",") if h.strip()]
    return not permitidos or (u.hostname or "").lower() in permitidos


class Pedido(BaseModel):
    audio_url: str
    callback_url: str
    job_id: str = Field(min_length=1, max_length=64)
    separar_fuentes: bool = False
    num: int = Field(default=4, ge=2, le=12)
    den: int = Field(default=4)


@app.get("/salud")
def salud() -> dict:
    c = analisis.Clasificador()
    return {"ok": True, "configurado": bool(secreto()), "clasificador": c.disponible}


@app.post("/transcribir", status_code=202)
async def transcribir(request: Request, tareas: BackgroundTasks):
    cuerpo = await request.body()
    if not firma_valida(cuerpo, request.headers.get(CABECERA)):
        return JSONResponse({"ok": False, "error": "Firma inválida."}, status_code=401)
    try:
        pedido = Pedido.model_validate_json(cuerpo)
    except Exception as e:  # noqa: BLE001
        return JSONResponse({"ok": False, "error": f"Pedido inválido: {e}"}, status_code=422)
    if not host_permitido(pedido.audio_url) or not host_permitido(pedido.callback_url):
        return JSONResponse({"ok": False, "error": "Host no permitido."}, status_code=422)
    tareas.add_task(procesar, pedido)
    return {"ok": True, "job_id": pedido.job_id}


def avisar(pedido: Pedido, datos: dict) -> None:
    cuerpo = json.dumps({"job_id": pedido.job_id, **datos}, separators=(",", ":")).encode()
    try:
        httpx.post(pedido.callback_url, content=cuerpo, timeout=30,
                   headers={CABECERA: firmar(cuerpo), "Content-Type": "application/json", "Accept": "application/json"})
    except Exception as e:  # noqa: BLE001
        log.warning("No se pudo avisar %s: %s", pedido.job_id, e)


def bajar(url: str, destino: str) -> None:
    total = 0
    with httpx.stream("GET", url, timeout=120, follow_redirects=False) as r:
        r.raise_for_status()
        with open(destino, "wb") as f:
            for parte in r.iter_bytes():
                total += len(parte)
                if total > MAX_BYTES:
                    raise ValueError("La grabación es demasiado grande.")
                f.write(parte)


def procesar(pedido: Pedido) -> None:
    carpeta = tempfile.mkdtemp(prefix="transcriptor-")
    ruta = os.path.join(carpeta, "audio")
    try:
        avisar(pedido, {"estado": "procesando", "progreso": 10})
        bajar(pedido.audio_url, ruta)
        avisar(pedido, {"estado": "procesando", "progreso": 30})
        res = analisis.transcribir(ruta, pedido.num, pedido.den, separar=pedido.separar_fuentes)
        avisar(pedido, {"estado": "listo", "progreso": 100, "resultado": res.como_dict()})
    except Exception as e:  # noqa: BLE001
        log.exception("Falló %s", pedido.job_id)
        avisar(pedido, {"estado": "error", "error": str(e)[:300]})
    finally:
        try:
            for a in os.listdir(carpeta):
                os.remove(os.path.join(carpeta, a))
            os.rmdir(carpeta)
        except OSError:
            pass
