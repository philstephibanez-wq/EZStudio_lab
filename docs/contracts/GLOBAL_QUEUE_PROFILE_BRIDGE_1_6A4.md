# LAB 1.6a4 — Adaptateur manuel PROFILE à Orchestrator

## Portée

Nouveau composant `GlobalQueueProfileBridge` et commande `lab:queue:run-profile`. Réutilisation **à l'identique** de `analysis/worker_entrypoint.py`, du runner PROFILE, de `ProfileExecutionService::completeFromJob`, de `GlobalQueuePersistedReceipt` et de la queue globale. Aucun fichier de moteur IA, modèle, checkpoint, preset ou paramètre scientifique n'est modifié. Les jobs `dispatch_owner=lab` et le job historique #201 ne sont jamais candidats.

## Sécurité / modes

- Désactivé par défaut (`EZSTUDIO_GLOBAL_QUEUE_EXECUTOR != 1`) ; commande en lecture seule sans `--apply` ; aucun lancement automatique ni tâche Windows.
- La commande exige un `--lab-job-id` explicite, un `global_queue_admission_outbox` `admitted`, un propriétaire `orchestrator`, un job `profile` `queued`, un identifiant global cohérent et un chemin Python local fourni explicitement. Aucune sélection globale implicite.
- Claim Orchestrator exact (`job_id`, `kind=profile`) et atomique ; le claim historique à deux champs reste inchangé. Respecte le slot GPU existant.
- Lance le worker existant sous un adaptateur Windows Job Object (CREATE_SUSPENDED, AssignProcessToJobObject, KILL_ON_JOB_CLOSE, contrôle de descendants avant retour 0) avec son enveloppe `ezscore.analysis-job.v2`. `EZS_TARGET=lab`, `EZS_EXPECTED_ROOT` corrects ; transmet ses paramètres existants, sans réanalyser les autres jobs.
- Vérifie le PID Python et son `CreationDate` via WMI/COM avant inscription immuable par l'endpoint loopback `ownership`; sinon refus fermé. La clé API ne traverse pas vers Python.
- Heartbeat toutes les 15 s, % <=99. Le statut scientifique et l'artefact sont persistés via le service existant, puis vérifiés par SHA256 et HMAC. Seul le reçu LAB autorise `finish` dans Orchestrator.
- En cas de panne, absence de WMI, perte réseau, collision ou échec Python : **aucune récupération ni deuxième exécution automatique** ; le lease reste occupé et requiert un diagnostic/attestation indépendante. L'absence d'un PID n'est jamais une preuve d'arrêt des enfants.
- La protection existante `trg_global_queue_admission_fence_status` n'est pas modifiée : l'état global terminal réside chez Orchestrator et `scientific_runs` porte `done` chez LAB ; `analysis_jobs.status` peut rester `queued`. Une projection unifiée UI reste à réaliser avant mise en service générale.

## Étude d'impact / critères bloquants

| Frontière | Avant | Après | Risque, garde-fou, rollback |
|---|---|---|---|
| Science | Runner PROFILE éprouvé | Identique | SHA Git des moteurs identique, tests de non-régression ; retour arrière du bridge seul |
| SQLite LAB | Outbox + fences | Inchangés | Aucun schéma ni déclencheur modifié, aucun replay historique |
| Queue globale | Claim général | Claim exact facultatif | L'API existante conserve son schéma ; test synthétique de sélection ; rollback des deux fichiers source O |
| Windows/GPU | Aucun worker global | Commande unique manuelle | WMI obligatoire ; aucune récupération automatique ; arrêt inconnu reste bloquant |
| Secrets | Credentials séparés | Exécuteur a son token propre | Loopback seulement ; clés non écrites dans Git/ZIP/log ; secrets retirés de l'env Python |
| Environnement | LAB/Orchestrator sur MAIN | Ports existants 8790 | Pas de tâche, service ou redémarrage automatique ; installer désactivé |

## Recette à exécuter avant activation

1. Préflight installation : branch/HEAD/VERSION/worktrees, nouveaux fichiers, ancrages de patch, UTF-8 strict sans BOM.
2. PHP lint fichiers touchés, PHPUnit ciblés dans les deux dépôts, suites PHPUnit complètes, Symfony `lint:container`, `lint:twig`, `git diff --check`, contrôle versions et actifs préexistants.
3. Tests HTTP 404 sans flag, 401/403 authentification, 422 schéma, claim exact GPU sur base isolée, ownership immuable, lease stale/refus, reçu HMAC. Aucune requête sur base MAIN pendant les tests.
4. Validation Windows réelle : COM/WMI, identité PID/CreationDate, Python utilisé par l'ancien worker, paramètres scientifiques, absence de console intrusive, mémoire/CPU/GPU, descendants actifs et absence de double exécution.
5. Uniquement après autorisation humaine : provisionner les clés hors Git, activer gates des deux composants, admettre un **nouveau** job synthétique ou autorisé, exécuter une commande explicite. Un crash ne permet jamais de décréter l'arbre Windows terminé.

**Important : installer le lot n'active ni les services ni la queue et ne traite aucun job existant. Le lot ne prétend pas prouver un fonctionnement opérationnel sur MAIN avant les tests des étapes 3–5.**
