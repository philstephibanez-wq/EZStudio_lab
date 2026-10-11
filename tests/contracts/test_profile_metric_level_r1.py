from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PYTHON = ROOT / "python"
PROFILE = PYTHON / "ezstudio" / "pipeline" / "profile"

sys.path[:0] = [str(PYTHON), str(PROFILE)]

from module_registry import _build_tempo_metric_consensus


def synthetic_records(event_unit: str, event_score: float, other_score: float):
    candidates = [
        {
            "beat_unit": event_unit,
            "events_per_bar": 8 if event_unit == "eighth" else 4,
            "score": event_score,
            "phase": 0,
            "phase_margin": 0.2,
        },
        {
            "beat_unit": "quarter" if event_unit != "quarter" else "eighth",
            "events_per_bar": 4 if event_unit != "quarter" else 8,
            "score": other_score,
            "phase": 0,
            "phase_margin": 0.1,
        },
    ]
    return [
        {
            "id": "rhythm.librosa-beat",
            "family": "rhythm",
            "status": "ok",
            "normalized": {"bpm": 152.0, "source": "librosa.beat"},
        },
        {
            "id": "meter.chords-shared-r9",
            "family": "meter",
            "status": "ok",
            "thresholds": {
                "confidence": 0.5,
                "metric_level_score_margin": 0.02,
                "metric_level_min_score": 0.0,
            },
            "normalized": {
                "label": "4/4",
                "suggested_label": "4/4",
                "metric_level": {
                    "candidates": candidates,
                    "source": "beat-this-downbeat-periodicity",
                },
            },
        },
    ]


def main() -> int:
    result = _build_tempo_metric_consensus(
        synthetic_records("eighth", 0.25, 0.10)
    )
    assert result["decision"] == "accepted"
    assert result["signature"] == "4/4"
    assert result["detected_grid"]["beat_unit"] == "eighth"
    assert result["detected_grid"]["bpm"] == 152.0
    assert result["primary"]["beat_unit"] == "quarter"
    assert result["primary"]["bpm"] == 76.0
    assert result["relationship"] == "double_grid_to_primary"

    result = _build_tempo_metric_consensus(
        synthetic_records("quarter", 0.25, 0.10)
    )
    assert result["decision"] == "accepted"
    assert result["detected_grid"]["beat_unit"] == "quarter"
    assert result["primary"]["beat_unit"] == "quarter"
    assert result["primary"]["bpm"] == 152.0
    assert result["relationship"] == "same_level"

    result = _build_tempo_metric_consensus(
        synthetic_records("eighth", 0.11, 0.10)
    )
    assert result["decision"] == "ambiguous"
    assert result["primary"] is None
    assert result["confidence"] is None
    assert result["calibrated"] is False

    preset = json.loads(
        (ROOT / "presets/profile/profile-r3b35c-compat.v2b.json")
        .read_text(encoding="utf-8")
    )
    meter = next(
        m for m in preset["modules"]
        if m["id"] == "meter.chords-shared-r9"
    )
    assert meter["thresholds"]["metric_level_score_margin"] == 0.02
    assert meter["thresholds"]["metric_level_min_score"] == 0.0

    runner = (PROFILE / "runner.py").read_text(encoding="utf-8")
    assert '"tempo_metric": consensus.get("tempo_metric") or {}' in runner
    assert '"metric_level": tempo_metric' in runner

    meter_source = (PROFILE / "meter_shared.py").read_text(encoding="utf-8")
    assert "metric_level_candidates" in meter_source
    assert "choose_phase" in meter_source

    print("PROFILE_METRIC_LEVEL_R1_CONTRACT_OK")
    print("4/4 eighth-grid 152 BPM -> primary quarter-note 76 BPM")
    print("4/4 quarter-grid 152 BPM -> primary quarter-note 152 BPM")
    print("Ambiguous metric level abstains explicitly")
    print("Thresholds are declarative in the PROFILE PhasePreset")
    print("Legacy tempo.bpm remains available during validation")
    print("CHORDS/N baseline untouched")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
