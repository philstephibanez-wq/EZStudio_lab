# LAB 1.6a17 — Recette et suppression de l'assertion de bootstrap historique

## Constat
Après installation de LAB 1.6a16, `pytest`: 6 tests réussis, avertissements de cache et AnyIO non bloquants. PHPUnit LAB: 34 tests, 2 échecs. Le contrôle de catalogue compare le cache-buster JS à VERSION (1.6a16) mais le Twig référence encore 1.6a15. Le contrôle legacy exige `scripts/start.ps1` bien qu'absent sur MAIN et non nécessaire au pont Orchestrator. Orchestrator non testé après ces échecs (arrêt fail-fast).

## Décision et impacts
- Le template conserve l'URL actuelle mais son cache-buster est aligné sur VERSION 1.6a17. Aucun JavaScript ni comportement métier modifié.
- Le test `LabWebLegacyContractTest.php` cesse d'exiger le lanceur obsolète et vérifie que le pont PROFILE ne le référence pas. L'existence du routeur HTTP LAB et de l'index reste exigée.
- Pas de changement au moteur PROFILE, worker, bridge 1.6a16, modèles, presets, schéma SQLite ou Orchestrator. Pas de start/stop ni claim GPU. Visibilité : le navigateur recharge le JS identique sous une nouvelle URL.
- Retour arrière par restauration des seuls trois fichiers existants avec empreintes, puis suppression du contrat ajouté ; aucun état scientifique modifié.

## Vérifications et limites
Preflight bloque si VERSION != 1.6a16, si les deux sources locales diffèrent des blobs GitHub audités, ou si les ancres sont absentes. Les fichiers modifiés par LAB 1.6a16 ne sont pas réécrits. Les sauvegardes et scripts restent dans H:/temp.

Après apply sur MAIN :

```powershell
cd H:\EZStudio_lab
php -l tests\Unit\LabWebLegacyContractTest.php
php bin\console lint:container
php bin\console lint:twig templates
php vendor\bin\phpunit --no-progress
if ($LASTEXITCODE -ne 0) { throw 'LAB_PHPUNIT_FAILED' }
git diff --check
git status --short
```

Refaire la recette Python 1.6a16 avec `PYTEST_DISABLE_PLUGIN_AUTOLOAD=1`, `-p no:cacheprovider`, et `PYTHONPATH=H:\temp\EZS_PROFILE_PYTEST_DEPS_1_6A16` pour éviter toute nouvelle écriture cache dans le dépôt. Vérifier ensuite le PHPUnit Orchestrator sans nouveau job.

L'installation n'est pas la validation du run scientifique ; LAB #203/global #2/run #178 demeure l'incident historique, sans replay.

## Commit mainteneur (après recette)
Titre suggéré : `[v1.6a17] fix(lab): aligner la recette et le cache JS sur le runtime unifié`
Corps : Contexte / Modifications / Validation / Impact / Limites et risques, avec preuves MAIN réelles.
