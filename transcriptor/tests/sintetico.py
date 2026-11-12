"""Audio sintético a tempo conocido para los tests (sin archivos externos)."""
import numpy as np

SR = 22050


def surdo(f0=60.0, dur=0.35):
    t = np.arange(int(dur * SR)) / SR
    return np.sin(2 * np.pi * f0 * t) * np.exp(-t / 0.12)


def caja(dur=0.12, semilla=0):
    rng = np.random.default_rng(semilla)
    t = np.arange(int(dur * SR)) / SR
    ruido = rng.standard_normal(len(t))
    # Banda media: diferencia de dos suavizados (pasa-banda barato).
    k1 = np.convolve(ruido, np.ones(3) / 3, mode="same")
    k2 = np.convolve(ruido, np.ones(25) / 25, mode="same")
    return (k1 - k2) * np.exp(-t / 0.04) * 0.8


def agogo(f0=900.0, dur=0.2):
    t = np.arange(int(dur * SR)) / SR
    return (np.sin(2 * np.pi * f0 * t) + 0.6 * np.sin(2 * np.pi * f0 * 1.5 * t)) * np.exp(-t / 0.06) * 0.5


def pegar(y, golpe, t):
    i = int(t * SR)
    j = min(len(y), i + len(golpe))
    if i < len(y):
        y[i:j] += golpe[: j - i]


def ritmo(bpm=100.0, compases=12, inicio=0.5, acelera=0.0):
    """Surdo en 1 y 3, caja en 2 y 4, agogó en corcheas. Devuelve (audio, pulsos, golpes esperados)."""
    pulsos = []
    t = inicio
    for i in range(compases * 4 + 1):
        pulsos.append(t)
        t += 60.0 / (bpm + acelera * i)
    dur = pulsos[-1] + 1.0
    y = np.zeros(int(dur * SR))
    esperados = []
    for i, p in enumerate(pulsos[:-1]):
        if i % 4 in (0, 2):
            pegar(y, surdo(), p)
            esperados.append((p, "surdo"))
        else:
            pegar(y, caja(semilla=i), p)
            esperados.append((p, "redoblante"))
        medio = (p + pulsos[i + 1]) / 2
        pegar(y, agogo(), medio)
        esperados.append((medio, "agogo"))
    y += np.random.default_rng(1).standard_normal(len(y)) * 0.002
    return y / np.max(np.abs(y)), pulsos[:-1], esperados
