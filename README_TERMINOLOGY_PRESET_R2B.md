# EZStudio — TERMINOLOGY PRESET R2B

R2B renomme le contrat Python PROFILE actif sans modifier la logique scientifique.

## Chemins canoniques

- `profile/gene.py` → `profile/module.py`
- `profile/gene_registry.py` → `profile/module_registry.py`
- `profile/genes/` → `profile/modules/`
- `runtime/gene_spec.py` → `runtime/module_config.py`
- `adn/specs/` → `presets/`

Les anciens fichiers Python restent uniquement comme wrappers temporaires de compatibilité.

## Contrat sérialisé

- `gene_registry` → `module_registry`
- `genome` → `preset`
- `genes` → `modules`
- `gene` → `module`
- `ezstudio.gene-spec.v1` → `ezstudio.module-config.v1`
- `ezstudio.genome.region.v1` → `ezstudio.preset.phase.v1`
- `ezstudio.profile.genes.v1` → `ezstudio.profile.modules.v1`

## Invariant scientifique

Inchangés :
- CLAP `laion/clap-htsat-unfused`
- sample rate 48000
- chunk 10 s
- max_chunks 8
- taxonomies
- top-k 6 / 12 / 8 / 8
- comportement CUDA
- CHORDS/N

## Application

```powershell
cd H:\EZStudio_lab

tar -xf "$env:USERPROFILE\Downloads\EZStudio_TERMINOLOGY_PRESET_R2B.zip" -C H:\EZStudio_lab

powershell -ExecutionPolicy Bypass -File .\scripts\apply_terminology_preset_r2b.ps1

H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_profile_r3b35c_contract.py
H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_declarative_runtime_v1a.py
H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_declarative_runtime_v1b.py

php bin\console lint:container
php bin\console lint:twig templates
php bin\console cache:clear

git diff --check
git status --short
```

Après réussite : vrai run PROFILE avant toute évolution déclarative.
