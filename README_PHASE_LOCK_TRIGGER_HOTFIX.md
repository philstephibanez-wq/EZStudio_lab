# EZStudio — Phase lock trigger hotfix

## Cause

La colonne SQLite `analysis_resource_locks.region` a été renommée en `phase`,
mais les triggers SQLite existants ont conservé leur corps SQL historique :

```sql
l.region
```

Un `INSERT INTO analysis_jobs` déclenche donc :

```text
SQLSTATE[HY000]: no such column: l.region
```

## Correctif

Le hotfix :
- vérifie que la table possède `phase` et plus `region`;
- refuse de s'exécuter si un job est actif;
- supprime les variantes historiques des deux triggers;
- recrée :
  - `trg_analysis_jobs_phase_lock_insert`
  - `trg_analysis_jobs_phase_lock_claim`
- utilise exclusivement `l.phase`;
- exécute `foreign_key_check` et `integrity_check`.

## Application

```powershell
cd H:\EZStudio_lab

tar -xf "$env:USERPROFILE\Downloads\EZStudio_PHASE_LOCK_TRIGGER_HOTFIX.zip" -C H:\EZStudio_lab

powershell -ExecutionPolicy Bypass -File .\scripts\apply_phase_lock_trigger_hotfix.ps1
```

Attendu :

```text
TERMINOLOGY_PHASE_LOCK_TRIGGER_HOTFIX_OK
TERMINOLOGY_PHASE_LOCK_TRIGGER_HOTFIX_APPLIED
```

Ensuite relancer exactement le même PROFILE.
