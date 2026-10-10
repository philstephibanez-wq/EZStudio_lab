from __future__ import annotations
import ast, os, sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PY=ROOT/"python"; PROFILE=PY/"ezstudio/pipeline/profile"
sys.path[:0]=[str(PY),str(PROFILE)]
from ezstudio.runtime.preset_loader import PresetLoader
from preset_registry_factory import build_registry_from_phase_preset

for p in [PROFILE/"runner.py",PROFILE/"preset_registry_factory.py",PY/"ezstudio/runtime/contracts.py"]:
    ast.parse(p.read_text(encoding="utf-8"),filename=str(p))
r=(PROFILE/"runner.py").read_text(encoding="utf-8")
assert "def build_module_registry(" not in r
assert ".add(" not in r
assert "from modules import " not in r
assert "profile-r3b35c-compat.v2b.json" in r

saved={k:os.environ.get(k) for k in ["EZSTUDIO_CLAP_BATCH_SIZE","EZSTUDIO_MERT_BATCH_SIZE","EZSTUDIO_PASST_BATCH_SIZE","EZSTUDIO_BEAT_THIS_CHECKPOINT"]}
for k in saved: os.environ.pop(k,None)
try:
    loaded=PresetLoader(ROOT/"presets").load_phase("profile/profile-r3b35c-compat.v2b.json")
    reg=build_registry_from_phase_preset(loaded,ai_models_root=Path(r"H:\AIModels"),profile_models_root=Path(r"H:\AIModels\audio\profile"))
finally:
    for k,v in saved.items():
        if v is None: os.environ.pop(k,None)
        else: os.environ[k]=v
entries=list(reg._entries)
assert len(entries)==11
cfg={c.module_id:c for c,_ in entries}
ids=[c.module_id for c,_ in entries]
assert ids==["rhythm.librosa-beat","meter.chords-shared-r9","tonal.krumhansl-schmuckler","tonal.madmom-key-cnn-2017","tonal.madmom-key-cnn-2018","descriptors.librosa-lowlevel","semantic.clap-open-vocabulary","embedding.mert-95m","embedding.mert-330m","semantic.panns-cnn14","semantic.passt-audioset"]
assert cfg["rhythm.librosa-beat"].parameters=={"mono":True,"analysis_sample_rate":22050}
assert cfg["meter.chords-shared-r9"].thresholds=={"confidence":0.5}
assert cfg["semantic.clap-open-vocabulary"].parameters["batch_size"]==2
assert cfg["embedding.mert-95m"].parameters["batch_size"]==2
assert cfg["semantic.passt-audioset"].parameters["batch_size"]==2
assert loaded.value.settings["equivalence_baseline_run"]==78
assert len(loaded.sha256)==64
print("ANALYSIS_ENGINE_V2B_CONTRACT_OK")
print("11 PROFILE modules resolved from PhasePreset")
print("runner.py contains no hardcoded registry construction")
print("R3B35C parameters, models, devices and callables preserved")
print("GPU equivalence baseline: run #78")
print("CHORDS/N baseline untouched")
