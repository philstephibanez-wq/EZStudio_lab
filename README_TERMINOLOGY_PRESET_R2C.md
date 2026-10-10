# EZStudio — TERMINOLOGY PRESET R2C

R2C corrige les reliquats fonctionnels révélés par le grep R2B.

## Corrections importantes

1. `analysis_resource_locks`
   - BDD : colonne `phase`
   - code SQL : `phase`
   - supprime le dernier `INSERT ... region ...` incompatible avec le schéma R2A.

2. `ProfileValidationService`
   - toutes les clés sujet passent en `phase:*`
   - cohérent avec la migration des 59 validations humaines.

3. Nettoyage des derniers libellés visibles :
   - `ADN`
   - `Région PROFILE du chromosome`
   - `Gène/Région` dans le formulaire.

4. `PresetRegistry`
   - n'accepte plus la clé active legacy `genes`;
   - le contrat actif exige `modules`.

## Application

```powershell
cd H:\EZStudio_lab

tar -xf "$env:USERPROFILE\Downloads\EZStudio_TERMINOLOGY_PRESET_R2C.zip" -C H:\EZStudio_lab

powershell -ExecutionPolicy Bypass -File .\scripts\apply_terminology_preset_r2c.ps1

H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_profile_r3b35c_contract.py
H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_declarative_runtime_v1a.py
H:\Python\pythoncore-3.14-64\python.exe .\scripts\test_declarative_runtime_v1b.py

php bin\console lint:container
php bin\console lint:twig templates
php bin\console cache:clear

git diff --check
git status --short
```

Ensuite seulement : vrai run PROFILE de non-régression.
