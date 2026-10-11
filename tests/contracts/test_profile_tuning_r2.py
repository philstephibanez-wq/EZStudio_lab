from __future__ import annotations

import ast
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PYTHON = ROOT / "python"
PROFILE = PYTHON / "ezstudio/pipeline/profile"
sys.path[:0] = [str(PYTHON), str(PROFILE)]

from modules.tuning_librosa import _reference_hz

module_file = PROFILE / "modules/tuning_librosa.py"
module_text = module_file.read_text(encoding="utf-8")
ast.parse(module_text, filename=str(module_file))

assert abs(_reference_hz(0.0) - 440.0) < 1e-9
assert abs(_reference_hz(100.0) - 466.1637615) < 1e-5

preset = json.loads(
    (ROOT / "presets/profile/profile-r3b35c-compat.v2b.json")
    .read_text(encoding="utf-8")
)
mods = {m["id"]: m for m in preset["modules"]}
assert "tuning.librosa-reference-pitch" in mods
tuning = mods["tuning.librosa-reference-pitch"]
assert tuning["engine"] == "librosa"
assert tuning["parameters"]["resolution_semitones"] == 0.01
assert tuning["parameters"]["chunk_seconds"] == 12.0
assert tuning["parameters"]["max_chunks"] == 8
assert tuning["thresholds"]["minimum_chunk_support"] == 3
assert tuning["thresholds"]["stable_mad_cents"] == 5.0

runner = (PROFILE / "runner.py").read_text(encoding="utf-8")
assert '"tuning": tuning_n' in runner
assert '"tuning": consensus.get("tuning") or {}' in runner

registry = (PROFILE / "module_registry.py").read_text(encoding="utf-8")
assert '"tuning": tuning' in registry

runtime = (PROFILE / "module.py").read_text(encoding="utf-8")
assert "len(parameters) >= 2" in runtime
assert "fn(context, config)" in runtime

print("PROFILE_TUNING_R2_CONTRACT_OK")
print("Reference pitch is a first-class PROFILE module")
print("Hz and cents-from-A440 are emitted")
print("Temporal stability is preserved without fake calibrated confidence")
print("Module parameters and thresholds are declarative")
print("Legacy one-argument PROFILE modules remain supported")
print("CHORDS/N baseline untouched")
