import json
import os
import sys

import numpy as np
import pytest

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, RAIZ)
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app import analisis  # noqa: E402
from entrenamiento import generar  # noqa: E402

pytest.importorskip("torch")
pytest.importorskip("onnxruntime")
import onnxruntime as ort  # noqa: E402
import torch  # noqa: E402

from entrenamiento import entrenar as E  # noqa: E402


def partitura(tmp_path, celdas, tempo=120, nombre="p.json"):
    """celdas: {instrumento: [golpe o None por corchea]} en un compás 4/4 (8 corcheas)."""
    voces = {
        inst: [{"dur": "8", "stroke": g} if g else {"dur": "8", "rest": True} for g in lista]
        for inst, lista in celdas.items()
    }
    doc = {
        "version": 5, "title": "t", "tempo": tempo, "timeSignature": {"num": 4, "den": 4},
        "instruments": [{"id": i} for i in voces],
        "sections": [{"name": "A", "repeatX": 1, "measures": [{"voces": voces}]}],
    }
    ruta = tmp_path / nombre
    ruta.write_text(json.dumps(doc), encoding="utf-8")
    return str(ruta)


@pytest.fixture(scope="module")
def muestras():
    return generar.cargar_muestras()


def test_hay_27_muestras_reales(muestras):
    assert len(muestras) == 27
    assert all(len(v) > 1000 for v in muestras.values())


def test_mapeo_golpe_a_muestra(muestras):
    assert generar.resolver("surdo_grave", "nota", muestras) == ("surdo_grave_normal", 1.0)
    assert generar.resolver("surdo_grave", "acentuado", muestras) == ("surdo_grave_normal", 1.28)
    assert generar.resolver("timbal", "nota", muestras) == ("timbal_abierto", 1.0)
    assert generar.resolver("timbal", "slap", muestras) == ("timbal_slap", 1.0)
    assert generar.resolver("redoblante", "fantasma", muestras) == ("redoblante_normal", 0.35)
    # agogó no tiene «chapa»: cae a la nota normal
    assert generar.resolver("agogo", "chapa", muestras) == ("agogo_normal", 1.0)
    assert generar.resolver("todos", "nota", muestras) is None


def test_golpes_con_tiempo_del_tempo(tmp_path):
    ruta = partitura(tmp_path, {
        "surdo_grave": ["nota"] + [None] * 7,
        "redoblante": [None, None, "nota"] + [None] * 5,
    })
    golpes = generar.golpes_de_partitura(generar.cargar_partitura(ruta))
    assert [round(g.t, 3) for g in golpes] == [0.0, 0.5]  # a 120 bpm, la corchea dura 0,25 s, la 3ª está a 0,5 s
    assert golpes[0].inst == "surdo_grave" and golpes[1].inst == "redoblante"


def test_repeticiones_respetan_barras(tmp_path):
    doc = {"timeSignature": {"num": 4, "den": 4}, "tempo": 100, "sections": [
        {"repeatX": 2, "measures": [{"repeatBegin": True, "voces": {}}, {"repeatEnd": True, "voces": {}}, {"voces": {}}]},
    ]}
    assert generar.pasadas(doc) == [(0, 0), (0, 1), (0, 0), (0, 1), (0, 2), (0, 0), (0, 1), (0, 0), (0, 1), (0, 2)]


def test_aumentacion_cambia_la_senal_y_conserva_el_largo():
    rng = np.random.default_rng(3)
    y = np.sin(np.linspace(0, 200, 22050)).astype(np.float32) * 0.5
    z = generar.aumentar(y, rng)
    assert len(z) == len(y) and z.dtype == np.float32
    assert not np.allclose(z, y)


def test_ventanas_etiquetan_el_instrumento_del_golpe(tmp_path, muestras):
    ruta = partitura(tmp_path, {"surdo_grave": ["nota"] + [None] * 7}, tempo=120)
    X, Y = generar.construir([ruta], muestras, aumentaciones=1, negativas=0.0, seed=1)
    assert X.shape[1:] == (1, 64, 26) and len(X) == len(Y) == 1
    assert Y[0, analisis.INSTRUMENTOS.index("surdo_grave")] == 1.0
    assert Y[0].sum() == 1.0


def test_dataset_del_repo_cubre_los_instrumentos(muestras):
    rutas = generar.partituras_del_repo(12)
    golpes = [g for r in rutas for g in generar.golpes_de_partitura(generar.cargar_partitura(r))]
    insts = {g.inst for g in golpes}
    assert {"surdo_grave", "redoblante", "repique", "timbal"} <= insts


def test_entrenar_baja_la_perdida_y_onnx_carga(tmp_path, muestras):
    rutas = generar.partituras_del_repo(2)
    X, Y = generar.construir(rutas, muestras, aumentaciones=1, seed=0)
    X, Y = X[:300], Y[:300]
    modelo, historia = E.entrenar(X, Y, pasos=60, lote=64, semilla=0)
    assert np.mean(historia[-10:]) < np.mean(historia[:10]), "la pérdida tiene que bajar"

    ruta = E.exportar_onnx(modelo, str(tmp_path / "clasificador.onnx"))
    sesion = ort.InferenceSession(ruta, providers=["CPUExecutionProvider"])
    salida = sesion.run(None, {"mel": X[:4]})[0]
    assert salida.shape == (4, len(analisis.INSTRUMENTOS))
    with torch.no_grad():
        esperado = modelo(torch.from_numpy(X[:4])).numpy()
    assert np.allclose(salida, esperado, atol=1e-3)


def test_servicio_usa_el_onnx_si_existe(tmp_path, muestras, monkeypatch):
    rutas = generar.partituras_del_repo(1)
    X, Y = generar.construir(rutas, muestras, aumentaciones=1, seed=0)
    modelo, _ = E.entrenar(X[:200], Y[:200], pasos=10, lote=32, semilla=0)
    ruta = E.exportar_onnx(modelo, str(tmp_path / "c.onnx"))
    clasif = analisis.Clasificador(ruta)
    assert clasif.disponible
    seg = np.zeros(int(0.15 * analisis.SR), dtype=np.float32)
    seg[:200] = 0.3
    predicciones = clasif.predecir(seg)
    assert isinstance(predicciones, list)
    assert all(inst in analisis.INSTRUMENTOS and 0 <= conf <= 1 for inst, _, conf in predicciones)
    # Sin archivo, el servicio sigue con el fallback de reglas.
    assert not analisis.Clasificador(str(tmp_path / "no-existe.onnx")).disponible
