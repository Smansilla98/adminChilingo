"""Separación de fuentes (Demucs): apagada por defecto y sin romper el análisis si falta la dependencia."""
import os
import sys

import numpy as np
import soundfile as sf

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app import analisis, main  # noqa: E402
from sintetico import SR, ritmo  # noqa: E402


def _audio(tmp_path):
    y, _, _ = ritmo(bpm=100, compases=4)
    ruta = tmp_path / "ensayo.wav"
    sf.write(ruta, y, SR)
    return str(ruta)


def test_apagada_por_defecto():
    assert main.Pedido.model_fields["separar_fuentes"].default is False
    assert analisis.transcribir.__defaults__[2] is False  # parámetro separar


def test_sin_dependencia_no_falla_y_lo_informa(tmp_path, monkeypatch):
    monkeypatch.setattr(analisis, "demucs_disponible", lambda: False)
    assert analisis.separar_bateria(_audio(tmp_path)) is None
    res = analisis.transcribir(_audio(tmp_path), separar=True)
    assert res.metodo["separacion"] == "no_disponible"
    assert res.golpes  # el análisis sigue con la grabación completa


def test_si_el_proceso_falla_vuelve_a_la_grabacion(tmp_path, monkeypatch):
    import subprocess

    monkeypatch.setattr(analisis, "demucs_disponible", lambda: True)

    def roto(*a, **k):
        raise subprocess.CalledProcessError(1, "demucs")

    monkeypatch.setattr(subprocess, "run", roto)
    assert analisis.separar_bateria(_audio(tmp_path)) is None
    res = analisis.transcribir(_audio(tmp_path), separar=True)
    assert res.metodo["separacion"] == "fallo"
    assert res.tempo > 0


def test_sin_flag_no_se_intenta(tmp_path, monkeypatch):
    llamadas = []
    monkeypatch.setattr(analisis, "separar_bateria", lambda r: llamadas.append(r))
    res = analisis.transcribir(_audio(tmp_path))
    assert llamadas == []
    assert res.metodo["separacion"] is False
    assert np.isfinite(res.tempo)
