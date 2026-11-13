"""Entrena el clasificador chico de instrumentos (CNN sobre mel de 150 ms) y lo exporta a ONNX.

Solo para entrenar (requiere torch: ver entrenamiento/requirements.txt). El servicio no lo necesita:
si transcriptor/modelos/clasificador.onnx existe y onnxruntime está instalado, lo usa; si no, sigue
con el fallback de reglas de librosa.

Uso (desde transcriptor/):
    python -m entrenamiento.entrenar --aumentaciones 2 --pasos 1500 --salida modelos/clasificador.onnx
"""
from __future__ import annotations

import argparse
import os

import numpy as np

import torch
from torch import nn

from app.analisis import INSTRUMENTOS
from entrenamiento import generar

MODELO_DEFAULT = os.path.join(os.path.dirname(__file__), "..", "modelos", "clasificador.onnx")


class Clasificador(nn.Module):
    """Entrada (N, 1, 64, 26) mel. Salida: logits de 8 instrumentos (multi-etiqueta)."""

    def __init__(self, clases: int = len(INSTRUMENTOS)):
        super().__init__()

        def bloque(entra: int, sale: int) -> nn.Sequential:
            return nn.Sequential(
                nn.Conv2d(entra, sale, 3, padding=1, bias=False),
                nn.BatchNorm2d(sale),
                nn.ReLU(inplace=True),
                nn.MaxPool2d(2),
            )

        self.red = nn.Sequential(bloque(1, 16), bloque(16, 32), bloque(32, 64))
        self.cabeza = nn.Sequential(nn.AdaptiveAvgPool2d(1), nn.Flatten(), nn.Dropout(0.2), nn.Linear(64, clases))

    def forward(self, x: torch.Tensor) -> torch.Tensor:
        return self.cabeza(self.red(x))


def entrenar(
    X: np.ndarray,
    Y: np.ndarray,
    pasos: int = 1500,
    lr: float = 3e-3,
    lote: int = 128,
    semilla: int = 0,
    modelo: Clasificador | None = None,
) -> tuple[Clasificador, list[float]]:
    """Adam + BCE multi-etiqueta. Devuelve el modelo y la pérdida de cada paso (para verificar que baja)."""
    torch.manual_seed(semilla)
    rng = np.random.default_rng(semilla)
    modelo = modelo or Clasificador()
    opt = torch.optim.Adam(modelo.parameters(), lr=lr)
    lossf = nn.BCEWithLogitsLoss()
    Xt = torch.from_numpy(X)
    Yt = torch.from_numpy(Y)
    historia: list[float] = []
    modelo.train()
    for _ in range(pasos):
        idx = rng.integers(0, len(Xt), size=min(lote, len(Xt)))
        opt.zero_grad()
        perdida = lossf(modelo(Xt[idx]), Yt[idx])
        perdida.backward()
        opt.step()
        historia.append(float(perdida.item()))
    modelo.eval()
    return modelo, historia


def exportar_onnx(modelo: Clasificador, ruta: str) -> str:
    """ONNX con entrada «mel» (dinámica en el lote) y salida «logits», como espera app/analisis.py."""
    os.makedirs(os.path.dirname(os.path.abspath(ruta)), exist_ok=True)
    modelo.eval()
    ejemplo = torch.zeros(1, 1, 64, 26)
    torch.onnx.export(
        modelo,
        ejemplo,
        ruta,
        input_names=["mel"],
        output_names=["logits"],
        dynamic_axes={"mel": {0: "lote"}, "logits": {0: "lote"}},
        opset_version=17,
        dynamo=False,
    )
    return ruta


def precision(modelo: Clasificador, X: np.ndarray, Y: np.ndarray, umbral: float = 0.5) -> float:
    """Proporción de etiquetas acertadas (multi-etiqueta, umbral 0.5)."""
    modelo.eval()
    with torch.no_grad():
        p = torch.sigmoid(modelo(torch.from_numpy(X))).numpy()
    return float(((p >= umbral) == (Y >= 0.5)).mean())


def main() -> None:  # pragma: no cover - ejecución manual
    ap = argparse.ArgumentParser(description="Entrena el clasificador de percusión y exporta ONNX.")
    ap.add_argument("--aumentaciones", type=int, default=2)
    ap.add_argument("--partituras", type=int, default=None, help="cantidad de partituras del repo (todas por defecto)")
    ap.add_argument("--pasos", type=int, default=1500)
    ap.add_argument("--lote", type=int, default=128)
    ap.add_argument("--seed", type=int, default=0)
    ap.add_argument("--dataset", default=None, help="npz ya generado con entrenamiento/generar.py")
    ap.add_argument("--salida", default=MODELO_DEFAULT)
    a = ap.parse_args()

    if a.dataset:
        datos = np.load(a.dataset)
        X, Y = datos["X"], datos["Y"]
    else:
        X, Y = generar.construir(generar.partituras_del_repo(a.partituras), generar.cargar_muestras(), a.aumentaciones, seed=a.seed)
    orden = np.random.default_rng(a.seed).permutation(len(X))
    X, Y = X[orden], Y[orden]
    corte = int(len(X) * 0.9)
    modelo, historia = entrenar(X[:corte], Y[:corte], pasos=a.pasos, lote=a.lote, semilla=a.seed)
    print(f"ventanas {len(X)} · pérdida inicial {historia[0]:.3f} · final {historia[-1]:.3f}")
    print(f"precisión validación: {precision(modelo, X[corte:], Y[corte:]):.3f}")
    exportar_onnx(modelo, a.salida)
    print(f"ONNX en {a.salida}")


if __name__ == "__main__":  # pragma: no cover
    main()
