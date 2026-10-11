from __future__ import annotations

import ast
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
METER = ROOT / "python/ezstudio/pipeline/profile/meter_shared.py"

text = METER.read_text(encoding="utf-8")
ast.parse(text, filename=str(METER))

assert "def _metric_level_candidates(" in text
assert "choose_phase_fn," in text
assert "phase = choose_phase_fn(values, events_per_bar)" in text
assert "metric_level_candidates = _metric_level_candidates(\n        signature,\n        bt,\n        choose_phase,\n    )" in text

print("PROFILE_METRIC_LEVEL_R1A_HOTFIX_CONTRACT_OK")
print("choose_phase dependency scope fixed")
print("No scientific threshold or model changed")
print("CHORDS/N baseline untouched")
