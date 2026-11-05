# LAB 1.6a2 — Retrait du panneau distribué historique du Catalogue

## Constat vérifié

Le Catalogue `/home` de LAB 1.6a1 affiche « Exécution distribuée » avec les lignes enregistrées dans `analysis_workers` et les anciennes candidatures de `analysis_jobs`. Les entrées périmées (ex. nœud XPS) sont des **traces historiques**, non une attestation de machines disponibles. Le code actuel interroge `DistributedAnalysisLeaseService::workersSnapshot()` et `jobsSnapshot()` à chaque rendu, y compris leurs vérifications de compatibilité. La queue canonique moderne se trouve exclusivement dans Orchestrator ; LAB reste propriétaire de la vérité scientifique.

## Décision

Retirer **uniquement** le panneau de Catalogue et les deux lectures de snapshots déclenchées par `WorkbenchController::home()`. Conserver le Catalogue, l'import, les commandes PROFILE ALL, le parolier et les fonctions scientifiques. Ne pas substituer de nouveaux états inventés ni créer une autre vue concurrençant Orchestrator. La présentation des jobs de la queue globale reste du ressort de l'interface « Files & Jobs » d'Orchestrator.

Le test de contrat historique `tests/contracts/test_distributed_visibility_v1c3_contract.php`, qui exigeait expressément le panneau, est retiré car sa règle n'est plus applicable. Les contrats historiques demeurent dans Git. Le nouveau test PHPUnit pérennise la politique actuelle. Aucun effacement des tables `analysis_workers`, `analysis_job_leases`, `analysis_jobs`, des leases, des processus ou des secrets. Les endpoints distribués existants sont conservés, leur décommissionnement étant un **lot distinct après audit des consommateurs réels**.

## Impact et restauration

| Avant | Après | Risque | Prévention et recette | Rollback |
|---|---|---|---|---|
| Catalogue : panneau et lectures historiques DB | Catalogue sans panneau ni lectures | Régression des actions Catalogue | tests Twig / contrôleur, PHPUnit, recette navigateur | fichiers de Catalogue restaurés depuis backup |
| Entrées nœuds XPS périmées affichées | Non affichées | Confusion avec nettoyage des données | aucune instruction SQL ni suppression de tables | aucune donnée à restaurer |
| Queue Orchestrator 4.3a3 désactivée | Identique | conflit de consommateurs | aucun appel REST de claim ni activation | sans objet |
| Autres vues / endpoints distribués | Identiques | utilisateur d'API historique | classe et routes existantes inchangées | sans objet |
| LAB 1.6a1 | 1.6a2 | dérive de version | HEAD exact / worktree propre / version / backup avant apply | restaurer VERSION et fichiers |

## Suite distincte — raccordement d'exécution

Orchestrator 4.3a3 possède une passerelle de claim/heartbeat **dormante**. Ne l'activer qu'après définition du lanceur de confiance avec identité durable et supervision des descendants, de l'accusé de persistance scientifique LAB, et de la finalisation atomique sans double exécution. Ni l'exécution de Messenger ni la mise en queue des anciens jobs ne sont autorisées par ce lot.

## Recette

Installation : préflight sans écriture, apply avec backup `H:\temp`, rollback protégé. Tests : `php -l` des fichiers PHP concernés, PHPUnit ciblé, PHPUnit complet, `php bin/console lint:twig`, `lint:container`, `git diff --check`, contrôle UTF-8 sans BOM, affichage réel de `/home`, contrôle des jobs historiques sans mutation. Ne pas publier avant recette MAIN. Ne pas exécuter l'ancien contrat V1C3 retiré.
