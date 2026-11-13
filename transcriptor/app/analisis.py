"""Análisis de la grabación: pulsos, primer tiempo de cada compás y golpes por instrumento.

Orden de preferencia (todo en CPU):
  * Pulsos: Beat This! (CPJKU, MIT) si está instalado; si no, librosa.beat.beat_track
    + heurística de primer tiempo (el surdo grave marca el 1).
  * Golpes: el clasificador propio (modelos/clasificador.onnx, ver entrenamiento/) si
    existe; si no, reglas por bandas de frecuencia sobre librosa.onset_detect.

No se usan modelos de madmom (licencia CC BY-NC-SA, incompatible con el uso de la escuela).
"""
from __future__ import annotations

import os
from dataclasses import dataclass, field

import librosa
import numpy as np

SR = 22050
HOP = 256
VENTANA_S = 0.15  # ventana de análisis de cada golpe (también la del clasificador)

INSTRUMENTOS = [
    "surdo_grave", "surdo_medio", "surdo_agudo", "redoblante", "repique",
    "timbal", "agogo", "palmas",
]


@dataclass
class Resultado:
    tempo: float
    ts: dict
    beats: list[float]
    downbeats: list[float]
    golpes: list[dict] = field(default_factory=list)
    metodo: dict = field(default_factory=dict)

    def como_dict(self) -> dict:
        return {
            "tempo": round(float(self.tempo), 2),
            "ts": self.ts,
            "beats": [round(float(b), 4) for b in self.beats],
            "downbeats": [round(float(b), 4) for b in self.downbeats],
            "golpes": self.golpes,
            "metodo": self.metodo,
        }


def cargar(ruta: str) -> np.ndarray:
    y, _ = librosa.load(ruta, sr=SR, mono=True)
    if y.size == 0:
        raise ValueError("La grabación está vacía.")
    pico = float(np.max(np.abs(y)))
    return y / pico if pico > 0 else y


# --------------------------------------------------------------------------- pulsos

def _pulsos_beat_this(y: np.ndarray) -> tuple[list[float], list[float]] | None:
    try:  # pragma: no cover - dependencia opcional y pesada (torch)
        from beat_this.inference import Audio2Beats  # type: ignore
    except Exception:
        return None
    try:  # pragma: no cover
        a2b = Audio2Beats(checkpoint_path="final0", device="cpu", dbn=False)
        beats, downbeats = a2b(y, SR)
        return list(map(float, beats)), list(map(float, downbeats))
    except Exception:
        return None


def _primer_tiempo(y: np.ndarray, beats: np.ndarray, num: int) -> int:
    """Fase del compás: el pulso donde más suena lo grave (el surdo marca el 1)."""
    if len(beats) < num * 2:
        return 0
    graves = librosa.feature.rms(y=_filtro_pasabajos(y), frame_length=1024, hop_length=HOP)[0]
    frames = librosa.time_to_frames(beats, sr=SR, hop_length=HOP)
    frames = np.clip(frames, 0, len(graves) - 1)
    energia = graves[frames]
    puntajes = [float(np.mean(energia[k::num])) for k in range(num)]
    return int(np.argmax(puntajes))


def _filtro_pasabajos(y: np.ndarray, corte: float = 150.0) -> np.ndarray:
    from scipy.signal import butter, sosfiltfilt

    sos = butter(4, corte, btype="low", fs=SR, output="sos")
    return sosfiltfilt(sos, y)


def _autocorrelacion(env: np.ndarray) -> np.ndarray:
    env = env - env.mean()
    r = np.correlate(env, env, mode="full")[len(env) - 1:]
    return r / (r[0] + 1e-12)


def _en_retardo(r: np.ndarray, segundos: float) -> float:
    x = segundos * SR / HOP
    i = int(x)
    if i + 1 >= len(r):
        return 0.0
    f = x - i
    return float(r[i] * (1 - f) + r[i + 1] * f)


def estimar_tempo(env: np.ndarray, graves: np.ndarray, minimo: float = 70, maximo: float = 150) -> float:
    """Tempo de negra entre 70 y 150 BPM (el rango de los toques de la escuela).

    El problema clásico es la octava (la mitad o el doble): se elige el período que mejor
    explica a la vez todos los golpes y los graves (el surdo marca tiempos y medios compases).
    """
    ra, rg = _autocorrelacion(env), _autocorrelacion(graves)
    mejor, puntaje = 100.0, -np.inf
    for bpm in np.arange(minimo, maximo + 0.01, 0.25):
        p = 60.0 / bpm
        s = (_en_retardo(ra, p) + 0.5 * _en_retardo(ra, 2 * p) + _en_retardo(rg, 2 * p)
             + 0.5 * _en_retardo(rg, p) + 0.5 * _en_retardo(rg, 4 * p))
        if s > puntaje:
            mejor, puntaje = float(bpm), s
    return mejor


def _corregir_fase(beats: np.ndarray, env: np.ndarray, graves: np.ndarray) -> np.ndarray:
    """Si los pulsos cayeron a contratiempo (sobre las corcheas del agogó), los corre medio pulso:
    el pulso verdadero es el que tiene más graves."""
    if len(beats) < 4:
        return beats
    medio = np.diff(beats) / 2
    corridos = beats[:-1] + medio

    def peso(ts: np.ndarray) -> float:
        f = np.clip(librosa.time_to_frames(ts, sr=SR, hop_length=HOP), 0, len(graves) - 1)
        return float(np.mean(graves[f]) / (np.max(graves) + 1e-12) + 0.25 * np.mean(env[f]) / (np.max(env) + 1e-12))

    return corridos if peso(corridos) > peso(beats[:-1]) * 1.15 else beats


def pulsos(y: np.ndarray, num: int = 4) -> tuple[float, list[float], list[float], str]:
    bt = _pulsos_beat_this(y)
    if bt and len(bt[0]) >= 2:
        beats, downbeats = bt
        tempo = 60.0 / float(np.median(np.diff(beats)))
        return tempo, beats, downbeats, "beat_this"

    env = librosa.onset.onset_strength(y=y, sr=SR, hop_length=HOP)
    graves = librosa.onset.onset_strength(y=_filtro_pasabajos(y), sr=SR, hop_length=HOP)
    tempo = estimar_tempo(env, graves)
    _, frames = librosa.beat.beat_track(onset_envelope=env, sr=SR, hop_length=HOP, bpm=tempo, tightness=100)
    beats = librosa.frames_to_time(frames, sr=SR, hop_length=HOP)
    beats = _corregir_fase(beats, env, graves)
    if len(beats) >= 2:
        # Tempo según los pulsos encontrados (más fiel que la estimación global).
        tempo = 60.0 / float(np.median(np.diff(beats)))
    fase = _primer_tiempo(y, beats, num)
    downbeats = list(map(float, beats[fase::num]))
    return tempo, list(map(float, beats)), downbeats, "librosa"


# --------------------------------------------------------------------------- golpes

BANDAS = [(20, 120), (120, 250), (250, 1000), (1000, 4000), (4000, 11000)]


def _rasgos(seg: np.ndarray) -> dict:
    n = max(512, int(2 ** np.ceil(np.log2(len(seg)))))
    espectro = np.abs(np.fft.rfft(seg * np.hanning(len(seg)), n=n)) ** 2
    freqs = np.fft.rfftfreq(n, 1 / SR)
    total = float(np.sum(espectro)) + 1e-12
    bandas = [float(np.sum(espectro[(freqs >= a) & (freqs < b)])) / total for a, b in BANDAS]
    graves = (freqs >= 35) & (freqs < 200)
    pico_grave = float(freqs[graves][np.argmax(espectro[graves])]) if np.any(graves) else 0.0
    centroide = float(np.sum(freqs * espectro) / total)
    planitud = float(np.exp(np.mean(np.log(espectro + 1e-12))) / (np.mean(espectro) + 1e-12))
    medio = (freqs >= 250) & (freqs < 4000)
    planitud_media = float(np.exp(np.mean(np.log(espectro[medio] + 1e-12))) / (np.mean(espectro[medio]) + 1e-12)) if np.any(medio) else 0.0
    # Caída: cuánto queda de energía en la segunda mitad de la ventana (tapado = cae rápido).
    mitad = len(seg) // 2
    caida = float(np.sum(seg[mitad:] ** 2) / (np.sum(seg[:mitad] ** 2) + 1e-12))
    return {
        "bandas": bandas, "pico_grave": pico_grave, "centroide": centroide,
        "planitud": planitud, "planitud_media": planitud_media, "caida": caida,
    }


def clasificar_reglas(r: dict) -> list[tuple[str, str, float]]:
    """Instrumentos presentes en un golpe: [(inst, golpe, confianza)]. Reglas simples y explicables."""
    grave, medio_grave, medio, agudo, muy_agudo = r["bandas"]
    out: list[tuple[str, str, float]] = []
    bajos = grave + medio_grave
    if bajos > 0.35:
        pico = r["pico_grave"]
        inst = "surdo_grave" if pico < 70 else ("surdo_medio" if pico < 90 else "surdo_agudo")
        golpe = "tapado" if r["caida"] < 0.15 else "nota"
        conf = min(0.85, 0.4 + bajos * 0.5)
        if 62 <= pico <= 74 or 86 <= pico <= 94:  # en el borde entre surdos
            conf *= 0.6
        out.append((inst, golpe, conf))
    altos = agudo + muy_agudo
    if medio + altos > 0.3 and r["planitud_media"] < 0.06 and r["centroide"] > 600:
        # Metal afinado (pocas parciales, muy tonal): agogó.
        out.append(("agogo", "nota", min(0.8, 0.35 + (medio + altos) * 0.5)))
    elif medio + agudo > 0.3 and r["planitud_media"] > 0.3:
        # Ruido de banda media: bordona del redoblante (o palmas, si casi no hay graves).
        inst = "palmas" if bajos < 0.05 and r["centroide"] > 1800 and r["caida"] < 0.2 else "redoblante"
        out.append((inst, "nota", min(0.75, 0.3 + (medio + agudo) * 0.5)))
    elif medio + agudo > 0.3:
        inst = "repique" if r["centroide"] > 1500 else "timbal"
        golpe = "nota" if inst == "repique" else "abierto"
        out.append((inst, golpe, min(0.7, 0.3 + (medio + agudo) * 0.4)))
    return out


class Clasificador:
    """CNN propia exportada a ONNX (entrenamiento/entrenar.py). Si no está, se usan reglas."""

    def __init__(self, ruta: str | None = None):
        self.ruta = ruta or os.environ.get("TRANSCRIPTOR_MODELO", os.path.join(os.path.dirname(__file__), "..", "modelos", "clasificador.onnx"))
        self.sesion = None
        if os.path.exists(self.ruta):
            try:  # pragma: no cover - depende de onnxruntime y de un modelo entrenado
                import onnxruntime as ort  # type: ignore

                self.sesion = ort.InferenceSession(self.ruta, providers=["CPUExecutionProvider"])
            except Exception:
                self.sesion = None

    @property
    def disponible(self) -> bool:
        return self.sesion is not None

    def predecir(self, seg: np.ndarray) -> list[tuple[str, str, float]]:  # pragma: no cover
        from .mel import parche_mel

        x = parche_mel(seg)[None, None, :, :].astype(np.float32)
        prob = 1 / (1 + np.exp(-self.sesion.run(None, {"mel": x})[0][0]))
        return [(INSTRUMENTOS[i], "nota", float(p)) for i, p in enumerate(prob) if p >= 0.35]


def golpes(y: np.ndarray, clasificador: Clasificador | None = None) -> list[dict]:
    env = librosa.onset.onset_strength(y=y, sr=SR, hop_length=HOP)
    frames = librosa.onset.onset_detect(onset_envelope=env, sr=SR, hop_length=HOP, units="frames", backtrack=False, delta=0.07, wait=2)
    if len(frames) == 0:
        return []
    fuerza = env[frames]
    tope = float(np.percentile(fuerza, 95)) or 1.0
    largo = int(VENTANA_S * SR)
    out: list[dict] = []
    for f, s in zip(frames, fuerza):
        t = float(librosa.frames_to_time(f, sr=SR, hop_length=HOP))
        i0 = max(0, int(t * SR) - int(0.005 * SR))
        seg = y[i0:i0 + largo]
        if len(seg) < largo // 2:
            continue
        seg = np.pad(seg, (0, largo - len(seg)))
        if clasificador is not None and clasificador.disponible:
            detectados = clasificador.predecir(seg)
        else:
            detectados = clasificar_reglas(_rasgos(seg))
        vel = int(np.clip(40 + 87 * (s / tope), 1, 127))
        for inst, golpe, conf in detectados:
            if vel >= 118 and golpe in ("nota", "abierto"):
                golpe = "acentuado"
            out.append({"t": round(t, 4), "inst": inst, "golpe": golpe, "vel": vel, "conf": round(float(conf), 3)})
    return out


def demucs_disponible() -> bool:
    """¿Está instalado Demucs? Sin la dependencia, la separación se apaga sola (no falla el trabajo)."""
    import importlib.util

    return importlib.util.find_spec("demucs") is not None


def separar_bateria(ruta: str) -> str | None:
    """Pista de percusión con Demucs (opcional, con flag). Devuelve la ruta del WAV o None.

    Nunca lanza: si falta la dependencia, si el proceso falla o si no aparece la pista de batería,
    devuelve None y el análisis sigue con la grabación completa.
    """
    if not demucs_disponible():
        return None
    try:
        import subprocess
        import sys
        import tempfile

        salida = tempfile.mkdtemp(prefix="demucs-")
        subprocess.run(
            [sys.executable, "-m", "demucs", "--two-stems", "drums", "-n", "htdemucs", "-o", salida, ruta],
            check=True, timeout=1800, capture_output=True,
        )
        for raiz, _, archivos in os.walk(salida):
            for a in archivos:
                if a == "drums.wav":
                    return os.path.join(raiz, a)
    except Exception:  # noqa: BLE001 - la separación es opcional
        return None
    return None


def transcribir(ruta: str, num: int = 4, den: int = 4, separar: bool = False, clasificador: Clasificador | None = None) -> Resultado:
    metodo: dict = {"separacion": False}
    if separar:
        if not demucs_disponible():
            metodo["separacion"] = "no_disponible"
        else:
            pista = separar_bateria(ruta)
            if pista:
                ruta = pista
                metodo["separacion"] = True
            else:
                metodo["separacion"] = "fallo"
    y = cargar(ruta)
    tempo, beats, downbeats, m_pulsos = pulsos(y, num)
    clasificador = clasificador or Clasificador()
    lista = golpes(y, clasificador)
    metodo.update({"pulsos": m_pulsos, "golpes": "clasificador" if clasificador.disponible else "reglas"})
    return Resultado(tempo=tempo, ts={"num": num, "den": den}, beats=beats, downbeats=downbeats, golpes=lista, metodo=metodo)
