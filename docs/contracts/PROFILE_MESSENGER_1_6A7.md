# PROFILE — raccordement Messenger à l'outbox existante (LAB 1.6a7 / Orchestrator 4.3a6)

## Responsabilités conservées

- LAB : bouton **Analyser**, création d'un `scientific_run` et d'un `analysis_job`, outbox existante, moteur PROFILE r3b31 inchangé, persistance de l'artefact.
- Symfony Messenger LAB : **récepteur** de l'outbox LAB. Aucune nouvelle table ni file d'attente. Aucun dispatch du son, des modèles, des presets ou des chemins à Orchestrator.
- Orchestrator : admission REST et **attribution atomique du créneau GPU** via son système canonique. Aucune sonde GPU ajoutée par LAB.
- Bridge existant : exact claim, heartbeat, attestation de l'enfant, exécution de `analysis/worker_entrypoint.py`, preuve de persistance, finish signé. En cas de refus GPU (`job: null`), aucun `proc_open` ; le job demeure en attente.

## Activation et non-régression

- Le **worker Symfony Messenger** est `php bin/console messenger:consume profile_gpu --sleep=2`.
- Baseline `EZSTUDIO_PROFILE_WORKER_MIN_JOB_ID` fixée **une seule fois** avant le premier clic, puis préservée dans `.env.local`, pour ne jamais recycler les jobs antérieurs.
- Le worker est un processus **au premier plan**, lancé explicitement pour la recette : pas de tâche planifiée, démarrage permanent, service Windows ou gestion de PID ajoutée.
- `EZS_QUEUE_EXECUTOR_API`, jeton Executor et clé de preuve sont résolus depuis les variables PHP, et à défaut depuis les trois variables précisément autorisées de `.env.local` : nécessité opérationnelle du serveur HTTP actuel non Dotenv ; ni configuration globale importée, ni secret journalisé.
- L'interface lit un endpoint d'état PROFILE en GET par une Promise jQuery toutes les cinq secondes, pendant l'analyse uniquement. Le résultat final recharge la page existante.
- Sans autorisation GPU : le job reste `queued`. Sans preuve de persistance : pas de finish. Échec après claim : lease retenue, pas de deuxième exécution automatique (`retry_strategy.max_retries: 0`).
- STEMS, CHORDS, LYRICS, moteurs scientifiques, presets, modèles, routes PROD, ordonnanceur, et intention persistée des services restent inchangés.

## Séquence réelle de recette

1. `INSTALL.php preflight` vérifie versions, Git, sources et fichiers cibles ; ne modifie rien.
2. `INSTALL.php apply` sauvegarde tous les fichiers sources touchés, puis applique le lot.
3. PHPUnit complet des deux dépôts, Symfony `lint:container`, `lint:twig`, contrôles UTF-8 et état Git.
4. `GO_WORKER.ps1 -Mode preflight -Python ...` : deux POST volontairement invalides obtiennent HTTP 422 ; aucun job créé.
5. `GO_WORKER.ps1 -Mode run -Python ...` : photographie du plus grand ID existant une seule fois et lance **le consommateur Messenger** au premier plan.
6. Un seul clic **Analyser le profil** sur un morceau LAB autorisé. Constater l'admission REST, le claim GPU, les heartbeats, le `profile.json`, `scientific_runs.state=done` et la fin du job Orchestrator.
7. Comparer le résultat à un ancien run portant **le même audio hash et le même preset**. Ne pas déclarer de parité scientifique sur un autre enregistrement.

## Impacts et retour arrière

Avant : l'outbox pouvait admettre un job sans consommateur Messenger automatique. Après : un consommateur Symfony explicitement lancé utilise cette même outbox, sous contrôle du créneau GPU Orchestrator. Le seul processus ajouté au moment du test est le consommateur demandé ; le seul spawn scientifique est celui du bridge existant.

Risques : réactivation d'anciens jobs (bloquée par baseline), double exécution (exact claim atomique et statut scientifique), perte de job si GPU occupé (aucun ACK durable : nouvelle lecture), secret non chargé (API retourne 404/401 : blocage), interruption au milieu d'une analyse (lease et preuves conservées, sans reprise aveugle), serveur PHP conservant du code en cache (recette HTTP bloquante, pas de redémarrage global).

Pour restaurer le code, **arrêter volontairement le seul worker de recette** puis exécuter `ROLLBACK.php preflight <BACKUP>` et `ROLLBACK.php apply <BACKUP>`. Aucun fichier `.env.local`, état durable, job ou service n'est effacé automatiquement. Revalider l'état après restauration.

## Preuves requises

Tests syntaxiques et PHPUnit ne prouvent pas un run Windows. La recette utilisateur reste **NON VALIDÉE** tant qu'un nouvel enregistrement n'a pas atteint `done` avec reçu Orchestrator et artefact scientifique persisté.
