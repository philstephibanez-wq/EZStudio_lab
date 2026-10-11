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


def _chunk_estimates(
    y: np.ndarray,
    sr: int,
    *,
    chunk_seconds: float,
    max_chunks: int,
    resolution: float,
) -> list[float]:
    samples_per_chunk = max(1, int(round(float(chunk_seconds) * sr)))
    if y.size < samples_per_chunk:
        value = _estimate_cents(y, sr, resolution)
        return [] if value is None else [value]

    starts = np.linspace(
        0,
        max(0, y.size - samples_per_chunk),
        num=max(1, int(max_chunks)),
        dtype=int,
    )
    values = []
    for start in starts.tolist():
        chunk = y[start : start + samples_per_chunk]
        value = _estimate_cents(chunk, sr, resolution)
        if value is not None:
            values.append(value)
    return values


def run(
    context: ModuleContext,
    config: ModuleConfig,
) -> dict[str, Any]:
    parameters = config.parameters or {}
    thresholds = config.thresholds or {}

    resolution = float(parameters.get("resolution_semitones", 0.01))
    chunk_seconds = float(parameters.get("chunk_seconds", 12.0))
    max_chunks = int(parameters.get("max_chunks", 8))
    min_chunks = int(thresholds.get("minimum_chunk_support", 3))
    stable_mad_cents = float(thresholds.get("stable_mad_cents", 5.0))

    global_cents = _estimate_cents(
        np.asarray(context.y, dtype=np.float32),
        int(context.sr),
        resolution,
    )

    chunks = _chunk_estimates(
        np.asarray(context.y, dtype=np.float32),
        int(context.sr),
        chunk_seconds=chunk_seconds,
        max_chunks=max_chunks,
        resolution=resolution,
    )

    if global_cents is None:
        return {
            "status": "skipped",
            "raw": {
                "reason": "reference_pitch_unavailable",
                "chunk_estimates_cents": chunks,
            },
            "normalized": {
                "available": False,
                "reference_hz": None,
                "cents_from_a440": None,
                "stability": "unavailable",
                "confidence": None,
                "calibrated": False,
                "source": "librosa.estimate_tuning",
            },
        }

    median = float(np.median(chunks)) if chunks else global_cents
    mad = (
        float(np.median(np.abs(np.asarray(chunks) - median)))
        if len(chunks) >= 2
        else None
    )

    stable = (
        len(chunks) >= min_chunks
        and mad is not None
        and mad <= stable_mad_cents
    )

    normalized = {
        "available": True,
        "reference_hz": round(_reference_hz(global_cents), 3),
        "cents_from_a440": round(global_cents, 3),
        "stability": "stable" if stable else "uncertain",
        "stability_mad_cents": round(mad, 3) if mad is not None else None,
        "chunk_support": len(chunks),
        "minimum_chunk_support": min_chunks,
        "stable_mad_cents_threshold": stable_mad_cents,
        "confidence": None,
        "calibrated": False,
        "source": "librosa.estimate_tuning",
    }

    return {
        "raw": {
            "global_cents_from_equal_temperament": global_cents,
            "chunk_estimates_cents": [round(x, 4) for x in chunks],
            "chunk_median_cents": round(median, 4),
            "chunk_mad_cents": round(mad, 4) if mad is not None else None,
        },
        "normalized": normalized,
        "calibrated": {
            "status": "not_calibrated",
            "method": "temporal-stability-only",
            "confidence": None,
        },
        "engine": {"method": "estimate_tuning"},
    }
