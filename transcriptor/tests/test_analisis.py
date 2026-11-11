import os
import sys

import numpy as np
import pytest
import soundfile as sf

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app import analisis  # noqa: E402
from sintetico import SR, ritmo  # noqa: E402


@pytest.fixture(scope="module")
def grabacion(tmp_path_factory):
    y, pulsos, esperados = ritmo(bpm=100, compases=12)
    ruta = tmp_path_factory.mktemp("audio") / "ritmo.wav"
    sf.write(ruta, y, SR)
    return str(ruta), pulsos, esperados


@pytest.mark.parametrize("bpm", [84, 100, 126])
def test_tempo_dentro_de_3_bpm(tmp_path, bpm):
    y, _, _ = ritmo(bpm=bpm, compases=12)
    tempo, beats, _, metodo = analisis.pulsos(y)
    # El tracker puede elegir el doble o la mitad; la grilla se corrige al cuantizar, pero acá pedimos el tempo real.
    assert abs(tempo - bpm) <= 3, (tempo, bpm, metodo)
    assert len(beats) >= 40


def test_primer_tiempo_cae_en_el_surdo(grabacion):
    ruta, pulsos, _ = grabacion
    y = analisis.cargar(ruta)
    _, beats, downbeats, _ = analisis.pulsos(y)
    # El primer tiempo de compás tiene que coincidir con un pulso donde suena el surdo (pulsos 0, 4, 8...) o el 3 (2, 6...).
    surdos = np.array(pulsos[0::2])
    for d in downbeats[1:5]:
        assert np.min(np.abs(surdos - d)) < 0.04


def test_onsets_y_clases(grabacion):
    ruta, _, esperados = grabacion
    res = analisis.transcribir(ruta)
    tiempos = np.array([g["t"] for g in res.golpes])
    encontrados = 0
    for t, _ in esperados:
        if len(tiempos) and np.min(np.abs(tiempos - t)) < 0.035:
            encontrados += 1
    assert encontrados / len(esperados) >= 0.9

    def clase_en(t):
        cerca = [g for g in res.golpes if abs(g["t"] - t) < 0.035]
        return {g["inst"] for g in cerca}

    surdos = [t for t, c in esperados if c == "surdo"]
    acierto = sum(1 for t in surdos if any(c.startswith("surdo") for c in clase_en(t)))
    assert acierto / len(surdos) >= 0.8
    agogos = [t for t, c in esperados if c == "agogo"]
    assert sum(1 for t in agogos if "agogo" in clase_en(t)) / len(agogos) >= 0.7
    cajas = [t for t, c in esperados if c == "redoblante"]
    assert sum(1 for t in cajas if clase_en(t) & {"redoblante", "palmas"}) / len(cajas) >= 0.7
    for g in res.golpes:
        assert 0 <= g["conf"] <= 1 and 1 <= g["vel"] <= 127
    d = res.como_dict()
    assert d["ts"] == {"num": 4, "den": 4} and d["metodo"]["golpes"] == "reglas"


def test_aceleracion_leve_sigue_los_pulsos():
    y, pulsos, _ = ritmo(bpm=92, compases=12, acelera=0.25)
    _, beats, _, _ = analisis.pulsos(y)
    beats = np.array(beats)
    cerca = [np.min(np.abs(beats - p)) for p in pulsos[4:-4]]
    assert np.median(cerca) < 0.03
