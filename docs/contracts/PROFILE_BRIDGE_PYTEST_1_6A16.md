# LAB 1.6a16 — environnement PROFILE explicite et pytest

## Périmètre

- Exécution existante : `GlobalQueueProfileBridge` utilise un composant distinct `GlobalQueueProfileEnvironment` pour rétablir l'environnement LAB sans appeler `scripts/start.ps1`.
- Les paramètres hérités définis explicitement sont préservés, les chemins absents sont complétés par les valeurs de `scripts/start.ps1` publiées ; les secrets de queue sont retirés avant le spawn scientifique.
- En cas de sortie non nulle, les dernières 64 KiB de stdout/stderr du lanceur sont consignées dans `var/logs/jobs/job-XXXXXXXX-bridge.log`, **localement** et sans exposition Web. Les trois secrets de queue connus sont expurgés lorsqu'ils apparaissent. Les journaux peuvent contenir des chemins ou noms privés : ne pas les publier sans revue.
- Aucun changement dans `runner.py`, `worker_entrypoint.py`, `queue_profile_jobobject_launcher.py`, presets, modèles, SQLite, schémas, file, API ou PROD.

## Constat, limites, impacts

Échec constaté de LAB #203/global #2/run #178 (code 1), sans `job-00000203-profile.log` canonique : les sorties de l'enfant étaient collectées dans `$log` puis perdues. Cause racine du code retour 1 **inconnue**. Cette correction fournit une configuration reproductible et une trace utile pour une exécution *future* ; elle ne récupère pas rétroactivement les sorties perdues et ne répare pas automatiquement l'état local `queued` contre l'état global `failed`.

Impact : modification de l'environnement des **seuls futurs jobs PROFILE Orchestrator**, avec conservation des variables héritées, donc les valeurs nouvelles ne remplacent pas celles explicitement configurées. Création d'un fichier de log durable en cas d'échec. Pas de console, de reboot, de start/stop, de tâche planifiée ou de démarrage implicite. Les preuves des incidents précédents sont préservées. L'installation n'exécute aucun job et ne lance pas Messenger. Le rollback concerne seulement le **code** ; les runs passés et la queue ne sont pas modifiés.

## Recette par étapes

1. `INSTALL.py preflight` : branche main, version 1.6a15, SHA Git du pont, modifications locales refusées, ancrages et fichiers nouveaux ; aucun écrit.
2. `INSTALL.py apply` : sauvegarde sous `H:/temp`, 6 fichiers (dont VERSION 1.6a16), sans runtime.
3. `php -l` pour les trois fichiers PHP, `python -m pytest -q tests/contracts/test_profile_queue_runtime_1_6a16.py` ; **pytest ne fait aucun appel scientifique**.
4. `php vendor/bin/phpunit --no-progress` ; Orchestrator `php vendor/bin/phpunit --no-progress`, Symfony lint, Composer validate, `git diff --check`, UTF-8 sans BOM, inspection du worktree.
5. Recette opérationnelle avec **nouveau job autorisé**, après contrôle de la file globale ; ne pas rejouer #203. Vérifier le journal de pont en cas d'échec, et l'artefact + SHA en cas de succès.
6. Publication : utilisateur seul commit et push, message `[v1.6a16] fix(profile): fiabiliser environnement du pont et ajouter pytest`, corps `Contexte / Modifications / Validation / Impact / Limites`.

### Retour arrière séparé

`python H:\temp\EZS_PROFILE_BRIDGE_PYTEST_1_6A16\INSTALL.py rollback H:\temp\EZS_PROFILE_BRIDGE_1_6A16_BACKUP_...` (une fois le backup connu). L'installateur refuse le rollback si les fichiers installés ont été modifiés entre-temps. Les journaux d'incident restent intacts.

## Preuves

La syntaxe Python et PHP peut être contrôlée hors MAIN, mais la recette réelle et la parité scientifique Windows doivent être validées sur MAIN. Les tests pytest isolent une frontière ; ils ne remplacent pas une exécution musicale réelle.
