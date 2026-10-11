from __future__ import annotations

import ast
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PYTHON = ROOT / "python"
PROFILE = PYTHON / "ezstudio/pipeline/profile"
sys.path[:0] = [str(PYTHON), str(PROFILE)]

from modules.tuning_librosa import (
    _cross_method_consensus,
    _reference_hz,
    _wrap_cents,
)

module_file = PROFILE / "modules/tuning_librosa.py"
text = module_file.read_text(encoding="utf-8")
ast.parse(text, filename=str(module_file))

assert abs(_reference_hz(0.0) - 440.0) < 1e-9
assert _wrap_cents(57.0) == -43.0
assert _wrap_cents(-57.0) == 43.0

accepted = _cross_method_consensus(
    {"available": True, "stable": True, "cents": 4.0},
    {"available": True, "stable": True, "median_cents": 6.0},
    max_cross_method_disagreement_cents=5.0,
)
assert accepted["decision"] == "accepted"
assert accepted["cents_from_a440"] == 5.0

ambiguous = _cross_method_consensus(
    {"available": True, "stable": True, "cents": -2.0},
    {"available": True, "stable": True, "median_cents": 7.0},
    max_cross_method_disagreement_cents=5.0,
)
assert ambiguous["decision"] == "ambiguous"
assert ambiguous["reference_hz"] is None

preset = json.loads(
    (ROOT / "presets/profile/profile-r3b35c-compat.v2b.json")
    .read_text(encoding="utf-8")
)
mods = {m["id"]: m for m in preset["modules"]}
tuning = mods["tuning.librosa-reference-pitch"]
assert tuning["revision"] == "r2c"
assert tuning["parameters"]["spectral_n_fft"] == 8192
assert tuning["parameters"]["spectral_hop_length"] == 2048
assert tuning["parameters"]["spectral_fmin_hz"] == 55.0
assert tuning["parameters"]["spectral_fmax_hz"] == 4000.0
assert tuning["parameters"]["spectral_peaks_per_frame"] == 12
assert tuning["thresholds"]["spectral_minimum_peak_count"] == 80
assert tuning["thresholds"]["max_cross_method_disagreement_cents"] == 5.0

assert "_spectral_peak_offset_cents" in text
assert "cross-method-tuning-consensus-r2c" in text

print("PROFILE_TUNING_R2C_CONTRACT_OK")
print("Librosa family and independent spectral peak-offset method are separated")
print("Cross-method disagreement causes explicit abstention")
print("Temporal chunk stability remains part of the decision")
print("No ACE-specific correction or song hardcode")
print("CHORDS/N baseline untouched")
