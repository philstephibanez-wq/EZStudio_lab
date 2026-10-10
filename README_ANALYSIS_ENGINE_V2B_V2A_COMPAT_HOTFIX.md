# Analysis Engine V2B — V2A compatibility hotfix

## Cause

V2B ajoute deux champs nécessaires pour reconstruire exactement `ModuleConfig` :

- `display_name`
- `family`

Les anciens Presets V2A ne contiennent pas encore ces champs. Le loader V2B les exigeait donc immédiatement, ce qui cassait `test_analysis_engine_v2a.py`.

## Correctif

Compatibilité de lecture uniquement :

- `display_name` absent -> valeur `id`
- `family` absente -> préfixe de `id` avant le premier `.`

Pour V2B, les valeurs explicites du Preset restent prioritaires.

Aucun moteur, modèle, paramètre, seuil ou algorithme n'est modifié.

## Application

```powershell
cd H:\EZStudio_lab

tar -xf "$env:USERPROFILE\Downloads\EZStudio_ANALYSIS_ENGINE_V2B_V2A_COMPAT_HOTFIX.zip" -C H:\EZStudio_lab

powershell -ExecutionPolicy Bypass -File .\scripts\apply_analysis_engine_v2b_v2a_compat_hotfix.ps1
```

Attendu :

```text
ANALYSIS_ENGINE_V2B_V2A_COMPAT_HOTFIX_CONTRACT_OK
ANALYSIS_ENGINE_V2A_CONTRACT_OK
ANALYSIS_ENGINE_V2B_CONTRACT_OK
PROFILE_R3B35C_CONTRACT_OK
DECLARATIVE_RUNTIME_V1B_CONTRACT_OK
ANALYSIS_ENGINE_V2B_V2A_COMPAT_HOTFIX_OK
```
