"""Audio sintético etiquetado para el clasificador de percusión.

Renderiza las partituras del repo (database/data/partituras-v4/*.json) con las muestras reales de
public/sounds/perc, aplica aumentación (ruido, EQ, reverb simple, ganancia y desfase) y saca, por
cada golpe, una ventana de 150 ms con la etiqueta multi-hot de los instrumentos que tocan en ese
instante. Las ventanas se calculan con app.mel.parche_mel, la misma entrada que usa el servicio.

Mapeo golpe → muestra: espejo de resolverGolpe() de resources/js/partitura/samples.js.
"""
from __future__ import annotations

import glob
import json
import os
from dataclasses import dataclass

import numpy as np
import soundfile as sf
from scipy.signal import butter, fftconvolve, sosfilt

import librosa

from app.analisis import INSTRUMENTOS
from app.mel import LARGO, SR, parche_mel

RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
MUESTRAS = os.path.join(RAIZ, "public", "sounds", "perc")
PARTITURAS = os.path.join(RAIZ, "database", "data", "partituras-v4")

# Espejo de MAPA_SAMPLES / ARTICULACION_SAMPLE (instruments.js).
MAPA = {
    "surdo_grave": ["nota", "chapa", "tapado"],
    "surdo_medio": ["nota", "chapa", "tapado"],
    "surdo_agudo": ["nota", "chapa", "tapado"],
    "redoblante": ["nota", "acentuado", "chapa", "agudo"],
    "timbal": ["abierto", "slap", "palma", "presionado", "dedo"],
    "repique": ["nota", "acentuado", "chapa", "agudo"],
    "agogo": ["nota", "acentuado", "tapado"],
    "palmas": ["nota", "acentuado"],
}
ARTICULACION = {
    "nota": "normal", "acentuado": "acentuado", "chapa": "chapa", "tapado": "tapado",
    "abierto": "abierto", "slap": "slap", "palma": "palma", "presionado": "presionado",
    "dedo": "dedo", "agudo": "agudo", "fantasma": "normal", "flam": "normal",
}
# Ganancia por golpe (GOLPES[].gain en instruments.js).
GANANCIA_GOLPE = {"nota": 1.0, "acentuado": 1.2, "chapa": 0.85, "tapado": 0.6, "presionado": 0.55,
                  "abierto": 1.1, "slap": 1.15, "palma": 0.9, "dedo": 0.5, "agudo": 1.0, "flam": 1.0, "fantasma": 0.35}
DUR_TICKS = {"w": 192, "h": 96, "q": 48, "8": 24, "16": 12, "32": 6}
TPQ = 48
VENTANA_S = 0.15
PRE_S = 0.005          # igual que analisis.golpes(): la ventana empieza 5 ms antes del onset
TOLERANCIA_S = 0.012   # un golpe pertenece a la etiqueta si su onset cae a ±12 ms


@dataclass
class Golpe:
    t: float
    inst: str
    golpe: str
    amp: float


# --------------------------------------------------------------------------- muestras

def cargar_muestras(carpeta: str = MUESTRAS) -> dict[str, np.ndarray]:
    """Los WAV reales a SR, normalizados por pico (la ganancia relativa la pone GANANCIA_GOLPE)."""
    out: dict[str, np.ndarray] = {}
    for ruta in sorted(glob.glob(os.path.join(carpeta, "*.wav"))):
        y, sr = sf.read(ruta, dtype="float32")
        if y.ndim > 1:
            y = y.mean(axis=1)
        if sr != SR:
            y = librosa.resample(y, orig_sr=sr, target_sr=SR)
        pico = float(np.max(np.abs(y))) or 1.0
        out[os.path.basename(ruta)[:-4]] = (y / pico * 0.8).astype(np.float32)
    return out


def resolver(inst: str, golpe: str, muestras: dict) -> tuple[str, float] | None:
    """(nombre de la muestra, factor de ganancia). Espejo de resolverGolpe(); None si no hay sonido."""
    mapa = MAPA.get(inst)
    if not mapa:
        return None
    base = mapa[0]
    ganancia = 1.0
    if golpe in mapa:
        clave = golpe
    elif golpe == "fantasma":
        clave, ganancia = base, 0.35
    elif golpe == "acentuado":
        clave, ganancia = base, 1.28
    else:
        clave = base
    nombre = f"{inst}_{ARTICULACION.get(clave, clave)}"
    if nombre not in muestras:
        nombre = f"{inst}_normal"
    if nombre not in muestras:
        return None
    return nombre, ganancia


# --------------------------------------------------------------------------- partituras

def _ticks_nota(n: dict) -> int:
    base = DUR_TICKS.get(n.get("dur", "q"), TPQ)
    dots = max(0, min(2, int(n.get("dots") or 0)))
    t = base * {0: 1.0, 1: 1.5, 2: 1.75}[dots]
    tup = n.get("tuplet")
    if isinstance(tup, dict) and tup.get("num") and tup.get("den"):
        t = t * tup["den"] / tup["num"]
    return int(round(t))


def pasadas(score: dict, max_repeticiones: int = 2) -> list[tuple[int, int]]:
    """Orden de reproducción (barras de repetición y repeatX), como expandirTimeline()."""
    out: list[tuple[int, int]] = []
    for si, sec in enumerate(score.get("sections", [])):
        ms = sec.get("measures", [])
        pasada: list[tuple[int, int]] = []
        inicio = 0
        for mi, m in enumerate(ms):
            if m.get("repeatBegin"):
                inicio = mi
            pasada.append((si, mi))
            if m.get("repeatEnd"):
                pasada.extend((si, k) for k in range(inicio, mi + 1))
                inicio = mi + 1
        for _ in range(min(max(1, int(sec.get("repeatX", 1))), max_repeticiones)):
            out.extend(pasada)
    return out


def golpes_de_partitura(score: dict, max_repeticiones: int = 2) -> list[Golpe]:
    """Golpes con su instante en segundos, a partir del tempo y la firma de compás de la partitura."""
    ts = score.get("timeSignature") or {"num": 4, "den": 4}
    num, den = int(ts.get("num", 4)), int(ts.get("den", 4))
    if den not in (2, 4, 8, 16):
        den = 4
    capacidad = int(round(num * TPQ * 4 / den))
    seg_por_tick = 60.0 / max(30.0, float(score.get("tempo", 100))) / TPQ
    out: list[Golpe] = []
    cursor = 0
    for si, mi in pasadas(score, max_repeticiones):
        m = score["sections"][si]["measures"][mi]
        for inst, voz in (m.get("voces") or {}).items():
            if inst not in MAPA:
                continue
            local = 0
            for n in voz:
                if not n.get("rest"):
                    vel = n.get("vel")
                    amp = (float(vel) / 100.0) if vel else 1.0
                    amp *= GANANCIA_GOLPE.get(n.get("stroke") or "nota", 1.0)
                    out.append(Golpe(t=(cursor + local) * seg_por_tick, inst=inst, golpe=n.get("stroke") or "nota", amp=amp))
                local += _ticks_nota(n)
        cursor += capacidad
    out.sort(key=lambda g: g.t)
    return out


def cargar_partitura(ruta: str) -> dict:
    with open(ruta, encoding="utf-8") as f:
        return json.load(f)


# --------------------------------------------------------------------------- aumentación

def aumentar(y: np.ndarray, rng: np.random.Generator, intensidad: float = 1.0) -> np.ndarray:
    """Ruido de fondo, EQ (pasa-altos, pasa-bajos o pasa-banda), reverb simple y ganancia global."""
    y = y.astype(np.float32).copy()
    potencia = float(np.sqrt(np.mean(y**2)) + 1e-9)
    # EQ: un filtro al azar con probabilidad.
    if rng.random() < 0.6 * intensidad:
        tipo = rng.choice(["highpass", "lowpass", "bandpass"])
        if tipo == "highpass":
            sos = butter(2, rng.uniform(40, 400), btype="highpass", fs=SR, output="sos")
        elif tipo == "lowpass":
            sos = butter(2, rng.uniform(2500, 9000), btype="lowpass", fs=SR, output="sos")
        else:
            lo = rng.uniform(150, 800)
            sos = butter(2, [lo, min(lo * rng.uniform(2, 6), 10000)], btype="bandpass", fs=SR, output="sos")
        y = sosfilt(sos, y).astype(np.float32)
    # Reverb simple: cola exponencial de ruido, mezclada en húmedo.
    if rng.random() < 0.5 * intensidad:
        rt = rng.uniform(0.15, 0.7)
        t = np.arange(int(rt * SR)) / SR
        ir = (rng.standard_normal(len(t)) * np.exp(-6.9 * t / rt)).astype(np.float32)
        ir /= float(np.sqrt(np.sum(ir**2)) + 1e-9)
        wet = rng.uniform(0.1, 0.35)
        y = ((1 - wet) * y + wet * fftconvolve(y, ir)[: len(y)] * potencia).astype(np.float32)
    # Ruido de fondo a una relación señal/ruido aleatoria (blanco o filtrado).
    if rng.random() < 0.7 * intensidad:
        snr_db = rng.uniform(12, 35)
        ruido = rng.standard_normal(len(y)).astype(np.float32)
        if rng.random() < 0.5:
            ruido = sosfilt(butter(1, rng.uniform(300, 3000), btype="lowpass", fs=SR, output="sos"), ruido).astype(np.float32)
        ruido *= potencia / (np.sqrt(np.mean(ruido**2)) + 1e-9) * 10 ** (-snr_db / 20)
        y = y + ruido
    # Ganancia global de la toma.
    y *= float(rng.uniform(0.5, 1.0))
    return y.astype(np.float32)


# --------------------------------------------------------------------------- render y ventanas

def renderizar(golpes: list[Golpe], muestras: dict, rng: np.random.Generator, jitter_s: float = 0.004) -> tuple[np.ndarray, list[Golpe]]:
    """Mezcla los golpes. Cada uno con un pequeño desfase humano y ganancia al azar."""
    if not golpes:
        return np.zeros(SR, dtype=np.float32), []
    fin = max(g.t for g in golpes) + 0.6
    y = np.zeros(int(fin * SR) + SR // 2, dtype=np.float32)
    usados: list[Golpe] = []
    for g in golpes:
        r = resolver(g.inst, g.golpe, muestras)
        if r is None:
            continue
        nombre, ganancia = r
        s = muestras[nombre] * np.float32(g.amp * ganancia * rng.uniform(0.8, 1.2))
        t = max(0.0, g.t + rng.normal(0.0, jitter_s))
        i = int(t * SR)
        j = min(len(y), i + len(s))
        if i < len(y):
            y[i:j] += s[: j - i]
        usados.append(Golpe(t=t, inst=g.inst, golpe=g.golpe, amp=g.amp))
    return y, usados


def ventanas(y: np.ndarray, golpes: list[Golpe], rng: np.random.Generator, negativas: int = 0) -> tuple[np.ndarray, np.ndarray]:
    """Una ventana por golpe (mel + etiqueta multi-hot) y algunas ventanas vacías."""
    largo = int(VENTANA_S * SR)
    pre = int(PRE_S * SR)
    feats: list[np.ndarray] = []
    etiquetas: list[np.ndarray] = []
    tiempos_golpe = np.array([h.t for h in golpes], dtype=np.float64)
    clase_golpe = np.array([INSTRUMENTOS.index(h.inst) if h.inst in INSTRUMENTOS else -1 for h in golpes])
    for g in golpes:
        i0 = max(0, int(g.t * SR) - pre)
        seg = y[i0:i0 + largo]
        if len(seg) < largo // 2:
            continue
        seg = np.pad(seg, (0, largo - len(seg)))
        y_multi = np.zeros(len(INSTRUMENTOS), dtype=np.float32)
        cerca = np.abs(tiempos_golpe - g.t) <= TOLERANCIA_S
        for k in clase_golpe[cerca]:
            if k >= 0:
                y_multi[k] = 1.0
        feats.append(parche_mel(seg))
        etiquetas.append(y_multi)
    tiempos = np.array([g.t for g in golpes]) if golpes else np.array([])
    duracion = len(y) / SR
    creadas = 0
    intentos = 0
    while creadas < negativas and intentos < negativas * 20 and duracion > VENTANA_S + 0.2:
        intentos += 1
        t = rng.uniform(0, duracion - VENTANA_S)
        if tiempos.size and np.min(np.abs(tiempos - t)) < 0.1:
            continue
        i0 = int(t * SR)
        feats.append(parche_mel(y[i0:i0 + int(VENTANA_S * SR)]))
        etiquetas.append(np.zeros(len(INSTRUMENTOS), dtype=np.float32))
        creadas += 1
    if not feats:
        return np.zeros((0, 1, 64, 26), dtype=np.float32), np.zeros((0, len(INSTRUMENTOS)), dtype=np.float32)
    X = np.stack(feats)[:, None, :, :].astype(np.float32)
    return X, np.stack(etiquetas)


def construir(partituras: list[str], muestras: dict, aumentaciones: int = 2, negativas: float = 0.5, seed: int = 0) -> tuple[np.ndarray, np.ndarray]:
    """Dataset completo: cada partitura se renderiza `aumentaciones` veces con aumentación distinta."""
    rng = np.random.default_rng(seed)
    Xs, Ys = [], []
    for ruta in partituras:
        golpes = golpes_de_partitura(cargar_partitura(ruta))
        for _ in range(max(1, aumentaciones)):
            y, usados = renderizar(golpes, muestras, rng)
            y = aumentar(y, rng)
            X, Y = ventanas(y, usados, rng, negativas=int(len(usados) * negativas))
            if len(X):
                Xs.append(X)
                Ys.append(Y)
    if not Xs:
        return np.zeros((0, 1, 64, 26), dtype=np.float32), np.zeros((0, len(INSTRUMENTOS)), dtype=np.float32)
    return np.concatenate(Xs), np.concatenate(Ys)


def partituras_del_repo(limite: int | None = None) -> list[str]:
    # Solo las partituras numeradas (NN-slug.json); manifest.json es el índice, no una partitura.
    rutas = sorted(glob.glob(os.path.join(PARTITURAS, "[0-9][0-9]-*.json")))
    return rutas[:limite] if limite else rutas


if __name__ == "__main__":  # pragma: no cover
    import argparse

    p = argparse.ArgumentParser(description="Genera el dataset sintético de percusión (npz).")
    p.add_argument("salida", help="ruta del .npz")
    p.add_argument("--aumentaciones", type=int, default=2)
    p.add_argument("--partituras", type=int, default=None)
    p.add_argument("--seed", type=int, default=0)
    a = p.parse_args()
    X, Y = construir(partituras_del_repo(a.partituras), cargar_muestras(), a.aumentaciones, seed=a.seed)
    np.savez_compressed(a.salida, X=X, Y=Y)
    print(f"{len(X)} ventanas, clases {INSTRUMENTOS}")
