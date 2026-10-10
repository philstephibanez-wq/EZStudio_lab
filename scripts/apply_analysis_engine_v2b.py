from __future__ import annotations
import importlib, json, os, re
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
RUNTIME=ROOT/"python/ezstudio/runtime"
PROFILE=ROOT/"python/ezstudio/pipeline/profile"

for p in [
    RUNTIME/"analysis_engine.py",
    RUNTIME/"contracts.py",
    RUNTIME/"preset_loader.py",
    PROFILE/"module.py",
    PROFILE/"module_registry.py",
    PROFILE/"runner.py",
]:
    if not p.is_file():
        raise RuntimeError(f"V2A_checkpoint_missing:{p}")

def write(p:Path,s:str):
    p.parent.mkdir(parents=True,exist_ok=True)
    p.write_text(s,encoding="utf-8",newline="\n")

p=RUNTIME/"contracts.py"
s=p.read_text(encoding="utf-8")
old='''    adapter: str
    engine: str
    model: str | None = None
    device: str | None = None
    order: int = 0
    inputs: dict[str, Any] = field(default_factory=dict)
    settings: dict[str, Any] = field(default_factory=dict)
'''
new='''    adapter: str
    engine: str
    display_name: str
    family: str
    model: str | None = None
    model_path: Any = None
    engine_version: str | None = None
    device: str | None = None
    order: int = 0
    inputs: dict[str, Any] = field(default_factory=dict)
    parameters: dict[str, Any] = field(default_factory=dict)
    thresholds: dict[str, Any] = field(default_factory=dict)
    weights: dict[str, Any] = field(default_factory=dict)
    settings: dict[str, Any] = field(default_factory=dict)
    implementation: dict[str, Any] = field(default_factory=dict)
'''
if old not in s: raise RuntimeError("contracts_fields_anchor_missing")
s=s.replace(old,new)
old='''            adapter=_required_text(data.get("adapter"), "module.adapter"),
            engine=_required_text(data.get("engine"), "module.engine"),
            model=(str(data["model"]).strip() if data.get("model") else None),
            device=(str(data["device"]).strip() if data.get("device") else None),
            order=order,
            inputs=dict(data.get("inputs") or {}),
            settings=dict(data.get("settings") or {}),
'''
new='''            adapter=_required_text(data.get("adapter"), "module.adapter"),
            engine=_required_text(data.get("engine"), "module.engine"),
            display_name=_required_text(data.get("display_name"), "module.display_name"),
            family=_required_text(data.get("family"), "module.family"),
            model=(str(data["model"]).strip() if data.get("model") else None),
            model_path=data.get("model_path"),
            engine_version=(str(data["engine_version"]).strip() if data.get("engine_version") else None),
            device=(str(data["device"]).strip() if data.get("device") else None),
            order=order,
            inputs=dict(data.get("inputs") or {}),
            parameters=dict(data.get("parameters") or {}),
            thresholds=dict(data.get("thresholds") or {}),
            weights=dict(data.get("weights") or {}),
            settings=dict(data.get("settings") or {}),
            implementation=dict(data.get("implementation") or {}),
'''
if old not in s: raise RuntimeError("contracts_from_dict_anchor_missing")
s=s.replace(old,new)
write(p,s)

factory=r'''from __future__ import annotations
import importlib, os
from pathlib import Path
from typing import Any
from module import ModuleConfig
from module_registry import ModuleRegistry
from ezstudio.runtime.execution_plan import ExecutionPlanner
from ezstudio.runtime.preset_loader import LoadedPreset, PhasePreset

def _resolve(v:Any, roots:dict[str,Path])->Any:
    if isinstance(v,list): return [_resolve(x,roots) for x in v]
    if not isinstance(v,dict): return v
    if "$env_int" in v:
        n=str(v["$env_int"]); x=int(os.getenv(n,str(v.get("default",0))))
        if "min" in v: x=max(int(v["min"]),x)
        return x
    if "$env_path" in v:
        x=os.getenv(str(v["$env_path"]))
        return x if x else _resolve(v["default"],roots)
    if "$path" in v:
        a=v["$path"]; r=roots.get(str(a[0]))
        if r is None: raise ValueError(f"runtime_root_not_registered:{a[0]}")
        return str(r.joinpath(*[str(x) for x in a[1:]]))
    return {str(k):_resolve(x,roots) for k,x in v.items()}

def _callable(ref:str):
    if ":" not in ref: raise ValueError(f"callable_reference_invalid:{ref}")
    mod,attr=ref.split(":",1)
    if not (mod=="modules" or mod.startswith("modules.")):
        raise ValueError(f"callable_module_not_allowed:{mod}")
    fn=getattr(importlib.import_module(mod),attr,None)
    if not callable(fn): raise ValueError(f"callable_not_found:{ref}")
    return fn

def build_registry_from_phase_preset(
    loaded:LoadedPreset[PhasePreset], *,
    ai_models_root:Path, profile_models_root:Path
)->ModuleRegistry:
    if loaded.value.phase!="profile":
        raise ValueError(f"profile_phase_required:{loaded.value.phase}")
    roots={"ai_models_root":ai_models_root.resolve(),
           "profile_models_root":profile_models_root.resolve()}
    reg=ModuleRegistry()
    for row in ExecutionPlanner().build(loaded.value).modules:
        m=row.module
        ref=str(m.implementation.get("callable") or "")
        reg.add(ModuleConfig(
            module_id=m.id,display_name=m.display_name,family=m.family,
            order=m.order,engine=m.engine,engine_version=m.engine_version,
            model_id=m.model,
            model_path=(str(_resolve(m.model_path,roots)) if m.model_path is not None else None),
            device=m.device,
            parameters=_resolve(m.parameters,roots),
            thresholds=_resolve(m.thresholds,roots),
            weights=_resolve(m.weights,roots),
        ),_callable(ref))
    return reg
'''
write(PROFILE/"preset_registry_factory.py",factory)

mods=[
("rhythm.librosa-beat","Librosa Beat Tracker","rhythm","r3b35c","librosa",None,None,10,{"mono":True,"analysis_sample_rate":22050},{},{},None,"modules.rhythm_librosa:run"),
("meter.chords-shared-r9","CHORDS Shared Meter R9","meter","r9","chords_metric_r9","beat-this-final0","cuda:0",20,{},{"confidence":.50},{},{"$env_path":"EZSTUDIO_BEAT_THIS_CHECKPOINT","default":{"$path":["ai_models_root","audio","rhythm","beat-this","beat_this-final0.ckpt"]}},"modules.meter_chords_shared:run"),
("tonal.krumhansl-schmuckler","Krumhansl-Schmuckler Key","tonal","r3b35c","librosa-chroma-cqt+ks",None,None,30,{"chroma":"CQT"},{},{},None,"modules.tonal_ks:run"),
("tonal.madmom-key-cnn-2017","Madmom Key CNN 2017 Ensemble","tonal","2017","madmom-infer","CPJKU-key-cnn-2017-ensemble4","cpu:numpy",31,{},{},{},{"$path":["ai_models_root","audio","tonal","madmom-key-cnn","2017"]},"modules.tonal_madmom_key:run_2017"),
("tonal.madmom-key-cnn-2018","Madmom Genre-Agnostic Key CNN 2018","tonal","2018","madmom-infer","CPJKU-key-cnn-2018","cpu:numpy",32,{},{},{},{"$path":["ai_models_root","audio","tonal","madmom-key-cnn","2018","key_cnn.pkl"]},"modules.tonal_madmom_key:run_2018"),
("descriptors.librosa-lowlevel","Librosa Low-Level Descriptors","descriptors","r3b35c","librosa",None,None,40,{"rolloff_percent":.85},{},{},None,"modules.descriptors_librosa:run"),
("semantic.clap-open-vocabulary","CLAP Open Vocabulary","semantic","r3b35c-compat-v1","transformers.CLAP","laion/clap-htsat-unfused","cuda:0",60,{"chunk_seconds":10.0,"max_chunks":8,"taxonomy":"ezstudio-r3b10","batch_size":{"$env_int":"EZSTUDIO_CLAP_BATCH_SIZE","default":2,"min":1},"cuda_batching":True},{},{},{"$path":["profile_models_root","clap-htsat-unfused"]},"modules.semantic_clap_open_vocab:run"),
("embedding.mert-95m","MERT Music Embedding 95M","embedding","r3b35c","transformers.MERT","m-a-p/MERT-v1-95M","cuda:0",70,{"chunk_seconds":10.0,"max_chunks":6,"aggregation":"mean","dtype":"float16","batch_size":{"$env_int":"EZSTUDIO_MERT_BATCH_SIZE","default":2,"min":1}},{},{},{"$path":["ai_models_root","audio","embeddings","mert","MERT-v1-95M"]},"modules.embedding_mert:run_95m"),
("embedding.mert-330m","MERT Music Embedding 330M","embedding","r3b35c","transformers.MERT","m-a-p/MERT-v1-330M","cuda:0",71,{"chunk_seconds":10.0,"max_chunks":6,"aggregation":"mean","dtype":"float16","batch_size":{"$env_int":"EZSTUDIO_MERT_BATCH_SIZE","default":2,"min":1}},{},{},{"$path":["ai_models_root","audio","embeddings","mert","MERT-v1-330M"]},"modules.embedding_mert:run_330m"),
("semantic.panns-cnn14","PANNs CNN14 AudioSet","semantic","r3b35c","panns-inference","Cnn14_mAP=0.431","cuda:0",80,{"sample_rate":32000,"chunk_seconds":10.0,"max_chunks":8},{},{},{"$path":["ai_models_root","audio","semantic","panns","Cnn14_mAP=0.431.pth"]},"modules.semantic_panns:run"),
("semantic.passt-audioset","PaSST AudioSet Transformer","semantic","r3b35c","hear21passt","passt_s_swa_p16_128_ap476","cuda:0",81,{"sample_rate":32000,"chunk_seconds":10.0,"max_chunks":8,"batch_size":{"$env_int":"EZSTUDIO_PASST_BATCH_SIZE","default":2,"min":1}},{},{},{"$path":["ai_models_root","audio","semantic","passt","passt-s-f128-p16-s10-ap.476-swa.pt"]},"modules.semantic_passt:run"),
]
rows=[]
for id_,name,fam,rev,eng,model,dev,order,params,thr,wts,mpath,call in mods:
    d={"id":id_,"display_name":name,"family":fam,"revision":rev,"phase":"profile",
       "adapter":"python-callable","engine":eng,"order":order,
       "parameters":params,"thresholds":thr,"weights":wts,
       "implementation":{"callable":call}}
    if model: d["model"]=model
    if dev: d["device"]=dev
    if mpath is not None: d["model_path"]=mpath
    rows.append(d)
preset={"schema":"ezstudio.phase-preset.v1","id":"profile.r3b35c-compat","revision":"v2b","phase":"profile",
        "settings":{"migration_mode":"compatibility","scientific_baseline":"R3B35C","equivalence_baseline_run":78},
        "modules":rows,"outputs":{"schema":"ezstudio.profile.v1","compatibility_target":"profile-run-78"}}
write(ROOT/"presets/profile/profile-r3b35c-compat.v2b.json",json.dumps(preset,ensure_ascii=False,indent=2)+"\n")

p=PROFILE/"runner.py"; s=p.read_text(encoding="utf-8")
s=re.sub(r"(?:from modules import [^\n]+\n)+","",s)
anchor="from module_registry import ModuleRegistry\n"
if anchor not in s: raise RuntimeError("runner_import_anchor_missing")
s=s.replace(anchor,anchor+"from preset_registry_factory import build_registry_from_phase_preset\nfrom ezstudio.runtime.preset_loader import PresetLoader\n")
a=s.find("def build_module_registry("); b=s.find("\ndef main() -> int:",a)
if a<0 or b<0: raise RuntimeError("runner_builder_block_missing")
s=s[:a]+s[b+1:]
old="    module_registry = build_module_registry(ai_root, profile_root)\n"
new='''    preset_loader = PresetLoader(Path(__file__).resolve().parents[4] / "presets")
    loaded_phase_preset = preset_loader.load_phase(
        "profile/profile-r3b35c-compat.v2b.json"
    )
    module_registry = build_registry_from_phase_preset(
        loaded_phase_preset,
        ai_models_root=ai_root,
        profile_models_root=profile_root,
    )
'''
if old not in s: raise RuntimeError("runner_builder_call_missing")
s=s.replace(old,new)
old='"profile_architecture": "modules-r3b35c-canonical-contract",'
if old not in s: raise RuntimeError("runner_architecture_marker_missing")
s=s.replace(old,
'''"profile_architecture": "declarative-phase-preset-v2b",
            "profile_phase_preset": loaded_phase_preset.value.id,
            "profile_phase_preset_revision": loaded_phase_preset.value.revision,
            "profile_phase_preset_sha256": loaded_phase_preset.sha256,''')
write(p,s)

print("ANALYSIS_ENGINE_V2B_CODE_APPLIED")
print("PROFILE registry is now built from profile-r3b35c-compat.v2b.json")
print("runner.py hardcoded build_module_registry() removed")
print("R3B35C settings/callables preserved")
