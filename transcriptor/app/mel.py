"""Parche mel de ~150 ms: la entrada del clasificador propio (mismo cálculo al entrenar y al usarlo)."""
import librosa
import numpy as np

SR = 22050
N_MELS = 64
HOP = 128
N_FFT = 1024
LARGO = int(0.15 * SR)
FRAMES = 1 + LARGO // HOP  # 26


def parche_mel(seg: np.ndarray) -> np.ndarray:
    seg = np.asarray(seg, dtype=np.float32)
    if len(seg) < LARGO:
        seg = np.pad(seg, (0, LARGO - len(seg)))
    seg = seg[:LARGO]
    m = librosa.feature.melspectrogram(y=seg, sr=SR, n_fft=N_FFT, hop_length=HOP, n_mels=N_MELS, fmin=30, fmax=11000)
    m = librosa.power_to_db(m + 1e-10, ref=1.0)
    m = (m + 80.0) / 80.0
    return m[:, :FRAMES].astype(np.float32)
