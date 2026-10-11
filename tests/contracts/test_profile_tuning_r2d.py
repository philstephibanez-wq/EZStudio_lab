from __future__ import annotations

import ast
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PYTHON = ROOT / "python"
PROFILE = PYTHON / "ezstudio/pipeline/profile"
sys.path[:0] = [str(PYTHON), str(PROFILE)]

from modules.tuning_librosa import _consensus_r2d, _reference_hz

module_file = PROFILE / "modules/tuning_librosa.py"
text = module_file.read_text(encoding="utf-8")
ast.parse(text, filename=str(module_file))

assert abs(_reference_hz(0.0) - 440.0) < 1e-9

accepted = _consensus_r2d(
    {"available": True, "stable": True, "cents": 4.0},
    {
        "available": True,
        "stable": True,
        "median_cents": 6.0,
    },
    {"available": True, "cents": 6.0},
    max_cross_method_disagreement_cents=5.0,
)
assert accepted["decision"] == "accepted"
assert accepted["cents_from_a440"] == 5.0

ambiguous = _consensus_r2d(
    {"available": True, "stable": True, "cents": -7.0},
    {
        "available": True,
        "stable": True,
        "median_cents": 2.0,
    },
    {"available": True, "cents": 2.0},
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

assert tuning["revision"] == "r2d"
assert tuning["parameters"]["cqt_offset_min_cents"] == -30
assert tuning["parameters"]["cqt_offset_max_cents"] == 30
assert tuning["parameters"]["cqt_offset_step_cents"] == 2
assert tuning["parameters"]["cqt_fmin_hz"] == 55.0
assert tuning["parameters"]["cqt_n_octaves"] == 6
assert tuning["parameters"]["cqt_hop_length"] == 2048

assert "_cqt_detune_scan" in text
assert "cross-method-tuning-consensus-r2d" in text
assert "spectral_peak_offset" not in text

print("PROFILE_TUNING_R2D_CONTRACT_OK")
print("CQT detune scan replaces overtone-biased spectral peak offset")
print("Librosa family remains available as independent evidence")
print("Cross-method disagreement still causes abstention")
print("All scan parameters remain declarative")
print("No song-specific correction")
print("CHORDS/N baseline untouched")
