from __future__ import annotations

import math
from typing import Any

import numpy as np

from module import ModuleConfig, ModuleContext


def _reference_hz(cents_from_a440: float) -> float:
    return 440.0 * (2.0 ** (float(cents_from_a440) / 1200.0))


def _estimate_cents(y: np.ndarray, sr: int, resolution: float) -> float | None:
    import librosa

    if y.size < max(2048, int(sr * 0.25)):
        return None

    tuning_bins = librosa.estimate_tuning(
        y=np.asarray(y, dtype=np.float32),
        sr=int(sr),
        resolution=float(resolution),
        bins_per_octave=12,
    )
    value = float(tuning_bins) * 100.0
    if not math.isfinite(value):
        return None
    return value


def _chunk_slices(
    y: np.ndarray,
    sr: int,
    *,
    chunk_seconds: float,
    max_chunks: int,
) -> list[np.ndarray]:
    samples_per_chunk = max(1, int(round(float(chunk_seconds) * sr)))
    if y.size <= samples_per_chunk:
        return [np.asarray(y, dtype=np.float32)]

    starts = np.linspace(
        0,
        max(0, y.size - samples_per_chunk),
        num=max(1, int(max_chunks)),
        dtype=int,
    )
    return [
        np.asarray(y[start : start + samples_per_chunk], dtype=np.float32)
        for start in starts.tolist()
    ]


def _chunk_estimates_librosa(
    y: np.ndarray,
    sr: int,
    *,
    chunk_seconds: float,
    max_chunks: int,
    resolution: float,
) -> list[float]:
    values = []
    for chunk in _chunk_slices(
        y,
        sr,
        chunk_seconds=chunk_seconds,
        max_chunks=max_chunks,
    ):
        value = _estimate_cents(chunk, sr, resolution)
        if value is not None:
            values.append(value)
    return values


def _wrap_cents(value: float) -> float:
    wrapped = ((float(value) + 50.0) % 100.0) - 50.0
    if wrapped == -50.0:
        return 50.0
    return wrapped


def _circular_cents(values: list[float], weights: list[float]) -> float | None:
    if not values or len(values) != len(weights):
        return None
    angles = np.asarray(values, dtype=float) * (2.0 * np.pi / 100.0)
    w = np.asarray(weights, dtype=float)
    if not np.any(w > 0):
        return None
    x = float(np.sum(w * np.cos(angles)))
    y = float(np.sum(w * np.sin(angles)))
    if abs(x) < 1e-12 and abs(y) < 1e-12:
        return None
    angle = math.atan2(y, x)
    return _wrap_cents(angle * 100.0 / (2.0 * np.pi))


def _spectral_peak_offset_cents(
    y: np.ndarray,
    sr: int,
    *,
    n_fft: int,
    hop_length: int,
    fmin_hz: float,
    fmax_hz: float,
    peaks_per_frame: int,
    minimum_peak_count: int,
) -> float | None:
    import librosa

    if y.size < n_fft:
        return None

    spec = np.abs(
        librosa.stft(
            np.asarray(y, dtype=np.float32),
            n_fft=int(n_fft),
            hop_length=int(hop_length),
            window="hann",
            center=False,
        )
    )
    if spec.size == 0 or spec.shape[0] < 3:
        return None

    freqs = librosa.fft_frequencies(sr=int(sr), n_fft=int(n_fft))
    lo = int(np.searchsorted(freqs, float(fmin_hz), side="left"))
    hi = int(np.searchsorted(freqs, float(fmax_hz), side="right"))
    lo = max(1, lo)
    hi = min(spec.shape[0] - 1, hi)
    if hi <= lo:
        return None

    cents_values: list[float] = []
    weights: list[float] = []
    eps = 1e-12

    for frame in range(spec.shape[1]):
        mag = spec[:, frame]
        region = mag[lo:hi]
        if not np.any(region > 0):
            continue

        center = region
        left = mag[lo - 1 : hi - 1]
        right = mag[lo + 1 : hi + 1]
        mask = (center > left) & (center >= right)
        idx = np.flatnonzero(mask) + lo
        if idx.size == 0:
            continue

        if idx.size > int(peaks_per_frame):
            ranked = np.argsort(mag[idx])[-int(peaks_per_frame):]
            idx = idx[ranked]

        for k in idx.tolist():
            alpha = math.log(float(mag[k - 1]) + eps)
            beta = math.log(float(mag[k]) + eps)
            gamma = math.log(float(mag[k + 1]) + eps)
            denom = alpha - 2.0 * beta + gamma
            delta = 0.0 if abs(denom) < 1e-12 else 0.5 * (alpha - gamma) / denom
            delta = float(np.clip(delta, -0.5, 0.5))

            freq = (float(k) + delta) * float(sr) / float(n_fft)
            if freq <= 0.0:
                continue

            midi = 69.0 + 12.0 * math.log2(freq / 440.0)
            cents = _wrap_cents((midi - round(midi)) * 100.0)
            cents_values.append(cents)
            weights.append(float(mag[k]) ** 0.5)

    if len(cents_values) < int(minimum_peak_count):
        return None

    return _circular_cents(cents_values, weights)


def _spectral_chunk_estimates(
    y: np.ndarray,
    sr: int,
    *,
    chunk_seconds: float,
    max_chunks: int,
    n_fft: int,
    hop_length: int,
    fmin_hz: float,
    fmax_hz: float,
    peaks_per_frame: int,
    minimum_peak_count: int,
) -> list[float]:
    values = []
    for chunk in _chunk_slices(
        y,
        sr,
        chunk_seconds=chunk_seconds,
        max_chunks=max_chunks,
    ):
        value = _spectral_peak_offset_cents(
            chunk,
            sr,
            n_fft=n_fft,
            hop_length=hop_length,
            fmin_hz=fmin_hz,
            fmax_hz=fmax_hz,
            peaks_per_frame=peaks_per_frame,
            minimum_peak_count=minimum_peak_count,
        )
        if value is not None:
            values.append(value)
    return values


def _summary(
    values: list[float],
    *,
    minimum_chunk_support: int,
    stable_mad_cents: float,
) -> dict[str, Any]:
    if not values:
        return {
            "available": False,
            "median_cents": None,
            "reference_hz": None,
            "chunk_estimates_cents": [],
            "chunk_mad_cents": None,
            "chunk_support": 0,
            "stable": False,
        }

    median = float(np.median(values))
    mad = (
        float(np.median(np.abs(np.asarray(values) - median)))
        if len(values) >= 2
        else None
    )
    stable = (
        len(values) >= int(minimum_chunk_support)
        and mad is not None
        and mad <= float(stable_mad_cents)
    )
    return {
        "available": True,
        "median_cents": round(median, 4),
        "reference_hz": round(_reference_hz(median), 3),
        "chunk_estimates_cents": [round(x, 4) for x in values],
        "chunk_mad_cents": round(mad, 4) if mad is not None else None,
        "chunk_support": len(values),
        "stable": stable,
    }


def _librosa_family(
    master_global: float | None,
    harmonic_global: float | None,
    master_summary: dict[str, Any],
    harmonic_summary: dict[str, Any],
    *,
    max_internal_disagreement_cents: float,
) -> dict[str, Any]:
    if master_global is None or harmonic_global is None:
        return {"available": False, "stable": False, "cents": None}

    disagreement = abs(float(master_global) - float(harmonic_global))
    stable = (
        bool(master_summary.get("stable"))
        and bool(harmonic_summary.get("stable"))
        and disagreement <= float(max_internal_disagreement_cents)
    )
    cents = float(np.median([master_global, harmonic_global]))
    return {
        "available": True,
        "stable": stable,
        "cents": round(cents, 4),
        "reference_hz": round(_reference_hz(cents), 3),
        "internal_disagreement_cents": round(disagreement, 4),
    }


def _cross_method_consensus(
    librosa_family: dict[str, Any],
    spectral: dict[str, Any],
    *,
    max_cross_method_disagreement_cents: float,
) -> dict[str, Any]:
    if (
        not librosa_family.get("available")
        or not spectral.get("available")
    ):
        return {
            "decision": "unavailable",
            "reference_hz": None,
            "cents_from_a440": None,
            "cross_method_disagreement_cents": None,
        }

    a = float(librosa_family["cents"])
    b = float(spectral["median_cents"])
    disagreement = abs(a - b)

    if (
        not bool(librosa_family.get("stable"))
        or not bool(spectral.get("stable"))
        or disagreement > float(max_cross_method_disagreement_cents)
    ):
        return {
            "decision": "ambiguous",
            "reference_hz": None,
            "cents_from_a440": None,
            "cross_method_disagreement_cents": round(disagreement, 4),
        }

    cents = float(np.median([a, b]))
    return {
        "decision": "accepted",
        "reference_hz": round(_reference_hz(cents), 3),
        "cents_from_a440": round(cents, 3),
        "cross_method_disagreement_cents": round(disagreement, 4),
    }


def run(
    context: ModuleContext,
    config: ModuleConfig,
) -> dict[str, Any]:
    import librosa

    p = config.parameters or {}
    t = config.thresholds or {}

    resolution = float(p.get("resolution_semitones", 0.01))
    chunk_seconds = float(p.get("chunk_seconds", 12.0))
    max_chunks = int(p.get("max_chunks", 8))
    hpss_margin = float(p.get("hpss_margin", 1.0))

    n_fft = int(p.get("spectral_n_fft", 8192))
    hop_length = int(p.get("spectral_hop_length", 2048))
    fmin_hz = float(p.get("spectral_fmin_hz", 55.0))
    fmax_hz = float(p.get("spectral_fmax_hz", 4000.0))
    peaks_per_frame = int(p.get("spectral_peaks_per_frame", 12))
    minimum_peak_count = int(t.get("spectral_minimum_peak_count", 80))

    min_chunks = int(t.get("minimum_chunk_support", 3))
    stable_mad = float(t.get("stable_mad_cents", 5.0))
    max_internal = float(t.get("max_estimator_disagreement_cents", 5.0))
    max_cross = float(t.get("max_cross_method_disagreement_cents", 5.0))

    master_y = np.asarray(context.y, dtype=np.float32)
    harmonic_y = np.asarray(
        librosa.effects.harmonic(master_y, margin=hpss_margin),
        dtype=np.float32,
    )

    master_global = _estimate_cents(master_y, int(context.sr), resolution)
    harmonic_global = _estimate_cents(harmonic_y, int(context.sr), resolution)

    master_values = _chunk_estimates_librosa(
        master_y,
        int(context.sr),
        chunk_seconds=chunk_seconds,
        max_chunks=max_chunks,
        resolution=resolution,
    )
    harmonic_values = _chunk_estimates_librosa(
        harmonic_y,
        int(context.sr),
        chunk_seconds=chunk_seconds,
        max_chunks=max_chunks,
        resolution=resolution,
    )
    spectral_values = _spectral_chunk_estimates(
        harmonic_y,
        int(context.sr),
        chunk_seconds=chunk_seconds,
        max_chunks=max_chunks,
        n_fft=n_fft,
        hop_length=hop_length,
        fmin_hz=fmin_hz,
        fmax_hz=fmax_hz,
        peaks_per_frame=peaks_per_frame,
        minimum_peak_count=minimum_peak_count,
    )

    master = _summary(
        master_values,
        minimum_chunk_support=min_chunks,
        stable_mad_cents=stable_mad,
    )
    master["global_cents"] = (
        round(master_global, 4) if master_global is not None else None
    )
    master["global_reference_hz"] = (
        round(_reference_hz(master_global), 3)
        if master_global is not None
        else None
    )

    harmonic = _summary(
        harmonic_values,
        minimum_chunk_support=min_chunks,
        stable_mad_cents=stable_mad,
    )
    harmonic["global_cents"] = (
        round(harmonic_global, 4) if harmonic_global is not None else None
    )
    harmonic["global_reference_hz"] = (
        round(_reference_hz(harmonic_global), 3)
        if harmonic_global is not None
        else None
    )

    spectral = _summary(
        spectral_values,
        minimum_chunk_support=min_chunks,
        stable_mad_cents=stable_mad,
    )

    librosa_family = _librosa_family(
        master_global,
        harmonic_global,
        master,
        harmonic,
        max_internal_disagreement_cents=max_internal,
    )
    consensus = _cross_method_consensus(
        librosa_family,
        spectral,
        max_cross_method_disagreement_cents=max_cross,
    )

    normalized = {
        "available": consensus["decision"] != "unavailable",
        "decision": consensus["decision"],
        "reference_hz": consensus["reference_hz"],
        "cents_from_a440": consensus["cents_from_a440"],
        "cross_method_disagreement_cents": consensus[
            "cross_method_disagreement_cents"
        ],
        "max_cross_method_disagreement_cents": max_cross,
        "methods": {
            "librosa_family": librosa_family,
            "spectral_peak_offset": {
                "available": spectral.get("available"),
                "stable": spectral.get("stable"),
                "reference_hz": spectral.get("reference_hz"),
                "cents_from_a440": spectral.get("median_cents"),
                "chunk_mad_cents": spectral.get("chunk_mad_cents"),
            },
        },
        "confidence": None,
        "calibrated": False,
        "source": "cross-method-tuning-consensus-r2c",
    }

    return {
        "raw": {
            "librosa_master": master,
            "librosa_harmonic": harmonic,
            "spectral_peak_offset": spectral,
            "librosa_family": librosa_family,
            "consensus": consensus,
        },
        "normalized": normalized,
        "calibrated": {
            "status": "not_calibrated",
            "method": "cross-method-tuning-consensus-r2c",
            "confidence": None,
        },
        "engine": {
            "method": "librosa-estimate-tuning+spectral-peak-offset",
        },
    }
