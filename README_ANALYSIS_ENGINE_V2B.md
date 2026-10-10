# EZStudio — Analysis Engine V2B

V2B branche réellement PROFILE sur un `PhasePreset`.

Le runner ne construit plus les 11 `ModuleConfig` en Python. Le fichier
`presets/profile/profile-r3b35c-compat.v2b.json` déclare ordre, moteur, modèle,
device, paramètres, seuils et callable de compatibilité.

Aucune mutation scientifique R3B35C. CHORDS/N reste inchangé.

```powershell
cd H:\EZStudio_lab
tar -xf "$env:USERPROFILE\Downloads\EZStudio_ANALYSIS_ENGINE_V2B.zip" -C H:\EZStudio_lab
powershell -ExecutionPolicy Bypass -File .\scripts\apply_analysis_engine_v2b.ps1
php bin\console cache:clear
git diff --check
git status --short
```

Après succès, lancer Breakfast in America en PROFILE. Baseline : run #78.
