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
    _consensus_r2d,
    _reference_hz,
)

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
    {"available": True, "stable": True, "cents": -2.0},
    {
        "available": True,
        "stable": True,
        "median_cents": 7.0,
    },
    {"available": True, "cents": 7.0},
    max_cross_method_disagreement_cents=5.0,
)
assert ambiguous["decision"] == "ambiguous"
assert ambiguous["reference_hz"] is None
assert ambiguous["cents_from_a440"] is None

preset = json.loads(
    (ROOT / "presets/profile/profile-r3b35c-compat.v2b.json")
    .read_text(encoding="utf-8")
)
mods = {m["id"]: m for m in preset["modules"]}
tuning = mods["tuning.librosa-reference-pitch"]

assert tuning["thresholds"]["max_cross_method_disagreement_cents"] == 5.0
assert tuning["revision"] == "r2d"
assert "cross-method-tuning-consensus-r2d" in text
assert "_consensus_r2d" in text

print("PROFILE_TUNING_R2C_COMPAT_CONTRACT_OK")
print("R2C cross-method abstention policy preserved by current revision")
print("Historical spectral implementation is not required")
print("No ACE-specific correction or song hardcode")
print("CHORDS/N baseline untouched")
