# LAB 1.6a5 — Projection d'état et première analyse PROFILE coordonnée

Ce lot finalise la présentation LAB d'un PROFILE délégué à Orchestrator sans modifier le moteur scientifique, les modèles, le preset, les paramètres ni `analysis/worker_entrypoint.py`.

Le statut de `analysis_jobs` demeure volontairement `queued` pour un job `dispatch_owner=orchestrator` (fence SQLite). La source de vérité scientifique devient `scientific_runs.state` pour les états `running`, `done`, `error` et les labels de progression produits par `ProfileExecutionService::progressFromJob`. Le Twig n'affiche plus indûment « PROFILE en attente » après une exécution dont la persistance scientifique est `done`. Les travaux conservant l'état `queued` restent affichés comme tels.

Le bridge existant continue à utiliser son cœur de lancement Windows Job Object validé R1C, son claim exact, son heartbeat et l'attestation HMAC. La seule addition lors d'un heartbeat est la projection du dernier pourcentage observé dans les labels scientifiques via le service canonique déjà présent. Aucun statut `analysis_jobs` n'est forcé. Sur erreur, le lease reste bloqué pour observation indépendante : aucun retry implicite.

Le kit opérationnel (hors dépôt) `PROFILE_RUN.ps1` / `VERIFY.php` permet de contrôler les gates, l'authentification via enveloppes invalides (422 sans écriture), le job explicitement sélectionné et la preuve finale. Les secrets restent hors Git. Aucun service ni job n'est activé par INSTALL.php.

## Impacts / retour arrière

- LAB VERSION 1.6a4 → 1.6a5, `src/Service/GlobalQueueProfileBridge.php`, `templates/workbench/profile.html.twig`, ce contrat et un test PHPUnit.
- Orchestrator inchangé (4.3a5). Aucune migration SQLite, aucune modification de files ou de données historiques, aucune intervention DEV/PROD, aucune tâche automatique ou console récurrente.
- La mise à jour de l'affichage dépend de la réussite du `scientific_runs.done` enregistré par LAB ; elle ne prétend pas que le job de queue physique est `completed` avant sa clôture attestée.
- Rollback contrôlé par ROLLBACK.php et la sauvegarde externe H:/temp ; ne jamais annuler ou rejouer un run scientifique réel au seul motif d'un problème d'affichage.

## Validation

Tests obligatoires sur MAIN : php -l des fichiers, Twig lint, conteneur Symfony, PHPUnit ciblé et complet des deux dépôts, statut Git, SHA, sonde HTTP avec payload invalides, test Windows synthétique déjà réalisé et **première exécution réelle** sur un nouveau job PROFILE explicitement autorisé. Les tests de lecture seule et les tests exécutés hors MAIN ne valent pas validation opérationnelle. Le rapport de référence archivé peut être incomplet ; comparer uniquement les observables réellement disponibles et la même version d'enregistrement/preset.
