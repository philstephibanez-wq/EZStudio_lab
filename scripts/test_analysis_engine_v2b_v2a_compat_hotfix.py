from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PYTHON = ROOT / "python"
if str(PYTHON) not in sys.path:
    sys.path.insert(0, str(PYTHON))

from ezstudio.runtime.preset_loader import PresetLoader

loader = PresetLoader(ROOT / "presets")

v2a = loader.load_phase("profile/profile-r3b35c-compat.v2a.json")
assert len(v2a.value.modules) == 11
assert v2a.value.modules[0].display_name == v2a.value.modules[0].id
assert v2a.value.modules[0].family == "rhythm"
assert v2a.value.modules[6].family == "semantic"

v2b = loader.load_phase("profile/profile-r3b35c-compat.v2b.json")
assert len(v2b.value.modules) == 11
assert v2b.value.modules[0].display_name == "Librosa Beat Tracker"
assert v2b.value.modules[0].family == "rhythm"
assert v2b.value.modules[6].display_name == "CLAP Open Vocabulary"
assert v2b.value.modules[6].family == "semantic"

print("ANALYSIS_ENGINE_V2B_V2A_COMPAT_HOTFIX_CONTRACT_OK")
print("V2A presets remain readable")
print("V2B explicit metadata remains authoritative")
