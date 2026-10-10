# EZStudio — PROFILE historical terminology compatibility hotfix

## Cause

R2B a correctement changé le contrat actif :

`gene` → `module`

Mais les anciens runs PROFILE déjà persistés contiennent légitimement encore :

```json
{"gene":"semantic.panns-cnn14"}
```

La vue Twig accédait directement à `e.module`, ce qui provoquait avec Twig strict :

`Key "module" ... does not exist`

## Correctif

Aucune donnée historique n'est réécrite.

La vue lit :

1. `module` pour un nouveau run ;
2. l'ancienne clé `gene` pour un run historique ;
3. `—` si aucune source n'est disponible.

Le fallback est limité à la frontière de présentation.

## Application

```powershell
cd H:\EZStudio_lab

tar -xf "$env:USERPROFILE\Downloads\EZStudio_TERMINOLOGY_PROFILE_HISTORY_COMPAT_HOTFIX.zip" -C H:\EZStudio_lab

powershell -ExecutionPolicy Bypass -File .\scripts\apply_terminology_profile_history_compat_hotfix.ps1

php bin\console lint:twig templates
php bin\console cache:clear
```

Attendu :

```text
TERMINOLOGY_PROFILE_HISTORY_COMPAT_HOTFIX_CONTRACT_OK
TERMINOLOGY_PROFILE_HISTORY_COMPAT_HOTFIX_OK
```

Ensuite recharger exactement le même ancien run PROFILE.
