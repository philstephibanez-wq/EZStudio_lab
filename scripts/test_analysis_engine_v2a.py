from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PYTHON = ROOT / "python"
if str(PYTHON) not in sys.path:
    sys.path.insert(0, str(PYTHON))

from ezstudio.runtime.analysis_engine import AnalysisEngine, AnalysisEngineError
from ezstudio.runtime.engine_registry import EngineAdapterRegistry
from ezstudio.runtime.preset_loader import PresetLoader


def main() -> int:
    loader = PresetLoader(ROOT / "presets")
    loaded = loader.load_phase("profile/profile-r3b35c-compat.v2a.json")

    preset = loaded.value
    assert preset.id == "profile.r3b35c-compat"
    assert preset.phase == "profile"
    assert len(preset.modules) == 11
    assert [m.order for m in preset.modules] == sorted(m.order for m in preset.modules)

    expected_ids = [
        "rhythm.librosa-beat",
        "meter.chords-shared-r9",
        "tonal.krumhansl-schmuckler",
        "tonal.madmom-key-cnn-2017",
        "tonal.madmom-key-cnn-2018",
        "descriptors.librosa-lowlevel",
        "semantic.clap-open-vocabulary",
        "embedding.mert-95m",
        "embedding.mert-330m",
        "semantic.panns-cnn14",
        "semantic.passt-audioset",
    ]
    assert [m.id for m in preset.modules] == expected_ids

    registry = EngineAdapterRegistry()
    registry.register(
        "legacy-profile-callable",
        lambda spec, context: {
            "module": spec["module"]["id"],
            "context": context,
            "adapter": "legacy-profile-callable",
        },
    )
    registry.register(
        "clap-zero-shot",
        lambda spec, context: {
            "module": spec["module"]["id"],
            "context": context,
            "adapter": "clap-zero-shot",
        },
    )

    engine = AnalysisEngine(registry)
    plan = engine.plan(loaded)
    assert len(plan.modules) == 11
    assert plan.modules[0].module.id == "rhythm.librosa-beat"
    assert plan.modules[-1].module.id == "semantic.passt-audioset"

    execution = engine.execute(
        loaded,
        lambda module: {"module_id": module.id},
    )
    assert len(execution.modules) == 11
    assert execution.modules[6].adapter == "clap-zero-shot"
    assert execution.provenance["phase_preset_sha256"] == loaded.sha256
    assert len(execution.provenance["execution_fingerprint"]) == 64
    assert execution.provenance["runtime_version"] == "2a"

    chain = loader.load_chain("chain/ezstudio-lab-compat.v2a.json")
    assert chain.value.phases[0].phase == "profile"
    assert chain.value.publication_gates["publish_to_ezscore"] is False

    # Explicit failure: no silent adapter fallback.
    empty_engine = AnalysisEngine(EngineAdapterRegistry())
    try:
        empty_engine.execute(loaded, lambda module: None)
    except AnalysisEngineError as exc:
        assert "adapter_not_registered" in str(exc)
    else:
        raise AssertionError("missing adapter must fail explicitly")

    print("ANALYSIS_ENGINE_V2A_CONTRACT_OK")
    print("11 PROFILE modules planned declaratively in deterministic order")
    print("Adapter dispatch works with explicit failure and no silent fallback")
    print("Preset provenance is SHA-256 fingerprinted")
    print("Production PROFILE runner intentionally unchanged in V2A")
    print("CHORDS/N baseline untouched")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
