# LAB 1.5a5 — nettoyage du démarrage web historique

## Propriété du serveur
Le service `EZS_FrankenPHP` est géré dans `EZS_orchestrator`. Le Caddyfile unifié héberge la façade LAB sur `8702` et l'application Symfony sur `8701`. LAB ne doit pas démarrer une seconde instance de Caddy ou le serveur `php -S`.

## Nettoyage démontrable
- `scripts/start.cmd` : wrapper d'un lancement de site historique, retiré uniquement lorsque le préflight ne détecte plus de référence Git ni de tâche Windows.
- `public/router.php` : routeur réservé au SAPI `cli-server` (`php -S`), **conservé temporairement** car l’ancien `start.ps1` peut encore en dépendre. Le retirer en même temps que ce lanceur, après preuve de non-usage et de reprise des variables scientifiques.
- `.phpunit.result.cache` : cache généré, ajouté à `.gitignore` et à retirer **de l'index Git** lors du commit (`git rm --cached -- .phpunit.result.cache`) sans supprimer la copie de travail.

## Éléments explicitement conservés
- `public/router.php` et `scripts/start.ps1` : ancienne paire de démarrage web, le script injectant aussi des variables de configuration scientifiques et de stockage (`AI_MODELS_ROOT`, `EZSTUDIO_*`, etc.). **Non supprimés** : vérifier d'abord que ces valeurs sont conservées hors de l'ancien processus de démarrage ; sa suppression sans cette preuve menacerait les moteurs scientifiques.
- `scripts/start-benchmark-worker.ps1`, scripts de publication des rapports et workers Python : hors périmètre de ce nettoyage web.

## Risques, preuve et restauration
Aucune modification du Caddyfile, des services Windows, des jobs, de la base SQLite, des modèles ni des fichiers d'état. Préflight : HEAD/branche/version, état local, intégrité des fichiers, références Git et tâches Windows. Après application : PHPUnit, route LAB et GET `8701/api/v1/service-status`, puis ping soft dans Orchestrator. Si un chemin est encore référencé, refuser la suppression et investiguer l'usage. Restaurer explicitement les sauvegardes conservées sous `H:\temp`, sans rollback automatique.
