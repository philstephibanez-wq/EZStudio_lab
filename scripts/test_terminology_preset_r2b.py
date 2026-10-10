from __future__ import annotations

import ast
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PROFILE = ROOT / "python/ezstudio/pipeline/profile"
RUNTIME = ROOT / "python/ezstudio/runtime"
CONFIG = ROOT / "presets/profile/semantic.clap-open-vocabulary.r3b35c.json"

required = [
    PROFILE/"module.py",
    PROFILE/"module_registry.py",
    PROFILE/"modules/semantic_clap_open_vocab.py",
    RUNTIME/"module_config.py",
    CONFIG,
]
for p in required:
    assert p.is_file(), p

for p in [
    PROFILE/"module.py",
    PROFILE/"module_registry.py",
    PROFILE/"runner.py",
    PROFILE/"canonical_profile.py",
    PROFILE/"perf_probe.py",
    RUNTIME/"module_config.py",
    RUNTIME/"adapters/clap_zero_shot.py",
]:
    ast.parse(p.read_text(encoding="utf-8"), filename=str(p))

cfg = json.loads(CONFIG.read_text(encoding="utf-8"))
assert cfg["schema"] == "ezstudio.module-config.v1"
assert cfg["module"]["id"] == "semantic.clap-open-vocabulary"
assert cfg["module"]["phase"] == "profile"
assert cfg["module"]["revision"] == "r3b35c-compat-v1"

families = cfg["taxonomy"]["families"]
assert len(families["genre"]) == 20
assert len(families["instrumentation"]) == 25
assert len(families["mood"]) == 20
assert len(families["voice"]) == 11
assert cfg["parameters"] == {
    "sample_rate": 48000,
    "chunk_seconds": 10.0,
    "max_chunks": 8,
}
assert cfg["decision"]["families"]["genre"] == {
    "mode": "compatibility_top_k", "top_k": 6
}
assert cfg["decision"]["families"]["instrumentation"] == {
    "mode": "compatibility_top_k", "top_k": 12
}
assert cfg["decision"]["families"]["mood"] == {
    "mode": "compatibility_top_k", "top_k": 8
}
assert cfg["decision"]["families"]["voice"] == {
    "mode": "compatibility_top_k", "top_k": 8
}

runner = (PROFILE/"runner.py").read_text(encoding="utf-8")
assert "from module import ModuleContext, ModuleConfig" in runner
assert "from module_registry import ModuleRegistry" in runner
assert "from modules import semantic_clap_open_vocab" in runner
assert '"module_registry": {' in runner
assert '"preset": preset_manifest' in runner
assert 'get("modules")' in runner
assert "écriture Preset R3B31" in runner
assert "genes-r3b35c-canonical-contract" not in runner

registry = (PROFILE/"module_registry.py").read_text(encoding="utf-8")
assert "def preset_manifest(" in registry
assert '"schema": "ezstudio.preset.phase.v1"' in registry
assert '"phase": "profile"' in registry
assert '"modules": modules' in registry
assert '"schema": "ezstudio.profile.modules.v1"' in registry
assert '"modules": records' in registry

runtime = (RUNTIME/"module_config.py").read_text(encoding="utf-8")
assert '"ezstudio.module-config.v1"' in runtime
assert 'self.data["module"]["id"]' in runtime
assert 'self.data["module"]["phase"]' in runtime
assert '"module_id": self.module_id' in runtime
assert '"module_revision": self.revision' in runtime
assert '"phase": self.phase' in runtime

assert "from module import" in (PROFILE/"gene.py").read_text(encoding="utf-8")
assert "from module_registry import" in (PROFILE/"gene_registry.py").read_text(encoding="utf-8")
assert "from .module_config import" in (RUNTIME/"gene_spec.py").read_text(encoding="utf-8")
assert not (ROOT/"adn/specs/profile/semantic.clap-open-vocabulary.r3b35c.json").exists()

print("TERMINOLOGY_PRESET_R2B_CONTRACT_OK")
print("PROFILE active contract: Module / Phase / Preset")
print("R3B35C scientific settings unchanged")
print("Legacy import aliases retained only as compatibility wrappers")
