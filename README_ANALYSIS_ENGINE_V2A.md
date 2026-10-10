# EZStudio — Analysis Engine V2A

## Périmètre

V2A introduit le **socle exécutable commun** du moteur déclaratif.

Cette livraison **ne bascule pas encore le runner PROFILE de production**. C'est volontaire :
le prochain incrément V2B branchera PROFILE sur ce moteur et imposera l'équivalence avec le run #78.

## Nouveaux composants

```text
python/ezstudio/runtime/
├── analysis_engine.py
├── contracts.py
├── execution_plan.py
├── model_resolver.py
├── preset_loader.py
└── provenance.py

schemas/
├── module-preset.v1.schema.json
├── phase-preset.v1.schema.json
└── chain-preset.v1.schema.json

presets/
├── profile/profile-r3b35c-compat.v2a.json
└── chain/ezstudio-lab-compat.v2a.json
```

## Invariants

- aucun changement scientifique PROFILE ;
- aucun changement CHORDS/N ;
- aucun fallback silencieux ;
- ordre d'exécution déterministe ;
- adapters référencés par nom stable ;
- provenance SHA-256 ;
- paramètres R3B35C recopiés dans le Preset de compatibilité ;
- `publish_to_ezscore=false` tant que les 4 phases ne sont pas migrées.

## UML

- `docs/analysis-engine-v2a.puml`
- `docs/analysis-engine-v2a.md` (Mermaid)

## Application

```powershell
cd H:\EZStudio_lab

tar -xf "$env:USERPROFILE\Downloads\EZStudio_ANALYSIS_ENGINE_V2A.zip" -C H:\EZStudio_lab

powershell -ExecutionPolicy Bypass -File .\scripts\apply_analysis_engine_v2a.ps1

H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_analysis_engine_v2a.py
H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_profile_r3b35c_contract.py
H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_declarative_runtime_v1a.py
H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_declarative_runtime_v1b.py

git diff --check
git status --short
```

## Attendu

```text
ANALYSIS_ENGINE_V2A_CONTRACT_OK
ANALYSIS_ENGINE_V2A_APPLIED
```

## Étape V2B

1. adapter générique vers les modules PROFILE existants ;
2. construction du `ModuleRegistry` depuis `PhasePreset` au lieu du hardcode Python ;
3. vrai run GPU ;
4. comparaison structurée avec le run #78 ;
5. aucune évolution scientifique tant que l'équivalence n'est pas prouvée.
