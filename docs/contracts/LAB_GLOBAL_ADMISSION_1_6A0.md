# EZStudio_lab 1.6a0 — Admission technique dans la queue globale

## Sources et compatibilité

- LAB : base stable `1.5a`, `main` SHA `7f8329bf0403f5b42897788dbcd098aab4cabb2c`.
- Orchestrator : `4.3a0`, `master` SHA `8d939f29c7ad4ff889cd9bce02908a54634a5c45`.
- Contrats canoniques : `CONTRAT DE DÉVELOPPEMENT.md`, `AGENTS.md` et Orchestrator `docs/contracts/QUEUE_ADMISSION_4_3A0.md`.
- L'application LAB conserve `analysis_jobs` et ses artefacts scientifiques. Orchestrator ne reçoit que l'enveloppe technique.

## Migration conservatrice

Une colonne `analysis_jobs.dispatch_owner` est ajoutée au premier accès DB après installation, avec `DEFAULT 'lab'`. **Tous les jobs historiques**, notamment #181/#182/#185 et les huit en attente observés le 04/11/2026, gardent cette valeur et restent inchangés. Aucune migration ou requête de publication des historiques.

Pour une nouvelle demande PROFILE, STEMS ou CHORDS :

- Si `EZSTUDIO_GLOBAL_QUEUE_ADMISSION` est absent ou différent de `1`, `dispatch_owner='lab'` et le comportement historique reste celui de LAB.
- Si `EZSTUDIO_GLOBAL_QUEUE_ADMISSION=1`, un Bearer valide `EZS_QUEUE_LAB_TOKEN` (32-256 caractères, fourni par l'environnement **du processus web LAB**, jamais par Git) est requis **avant insertion**. La demande est créée directement avec `dispatch_owner='orchestrator'` et une entrée `pending` dans `global_queue_admission_outbox`, atomiquement par déclencheur SQLite.
- Une requête HTTPS externe ou multi-hôte est interdite : uniquement `http://127.0.0.1:8790/api/v2/queue/jobs`, pair direct. Timeout de 3 secondes, aucune redirection, pas de sortie de token dans les journaux.
- Envelope : `external_id=analysis_job:<id>`, `idempotency_key=lab.analysis_job.<id>`, `kind=profile|stems|chords_scientific`, `resource_class=gpu`, `priority=50`. Pas de chanson/audio/chemin/paramètre scientifique dans la requête.
- HTTP 200 avec réponse corrélée : outbox `admitted`, identifiant global mémorisé. Autre code, indisponibilité ou réponse invalide : outbox reste `pending` avec un code d'erreur expurgé. La demande scientifique LAB reste `queued`.
- Un second appel de publication est sûr grâce à la clé d'idempotence. Reprise **manuelle et explicite uniquement** : `php bin/console lab:queue:publish-pending` (observation seule), puis `--apply` sur autorisation. Aucun scheduler ni worker supplémentaire.

## Séparation stricte des autorités

Les nouveaux jobs `dispatch_owner='orchestrator'` sont retirés de la liste LAB `analysisQueue()`, du `claimNextAnalysisJob()`, de la sélection distribuée et du `DistributedAnalysisLeaseService::claim()` direct. Une barrière SQLite interdit aussi leurs changements d'état ou leur suppression par les chemins historiques. **La finalisation et l'annulation Orchestrator restent à développer** ; un job global créé ici ne doit pas être consommé tant que ces protocoles et les leases de bout en bout ne sont pas vérifiés. Les nouvelles demandes globales ne sont donc pas encore exécutables.

Les jobs historiques `dispatch_owner='lab'` ne changent pas de chemin. Aucun moteur scientifique, modèle, stockage d'audio, compte, service Windows, Caddy, SSO, PROD ou Assistant n'est modifié par ce lot.

## Étude d'impact

| Avant | Après | Risque | Prévention / preuve | Retour arrière |
|---|---|---|---|---|
| `analysis_jobs` sans owner | Colonne ajoutée au premier accès, défaut `lab` | Modification du schéma | Uniquement SQLite LAB; anciens IDs/états conservés; test fixture | Ne pas supprimer colonne/DB ; le code peut être restauré seulement avant création de jobs globaux |
| Deux anciens chemins de claim LAB | Ils filtrent explicitement owner `lab` | Double exécution | Filtres SQL + garde du claim direct + trigger de clôture SQLite | Revenir au code ancien uniquement si **aucun** job global n'a été créé |
| Nouvelles demandes locales | Activation explicite possible vers la queue globale | Indisponibilité REST / job sans exécuteur | Outbox persistante + retry manuel, aucune admission automatique tant que désactivé | Couper le flag pour les futurs jobs ; conserver ceux déjà globaux |
| Aucune exécution Orchestrator | Toujours aucune | File globale avec jobs `queued` non exécutés | Avertissement explicite ; Messenger et callbacks non touchés | Pas de suppression de job distant |

## Recette MAIN et limites

Le préflight et l'installation ne doivent ni appeler REST, ni déclencher de worker, ni ouvrir la base scientifique. Sur MAIN : vérifier SHA/branche/version/état Git, syntaxe PHP, tests unitaires ciblés sur base SQLite jetable, PHPUnit complet, `lint:container`, commande `lab:queue:publish-pending` sans `--apply`, contrôle de l'absence de nouveaux jobs Orchestrator. Puis valider le mode désactivé avec un **nouveau job de test uniquement sous autorisation distincte**. En mode activé, l'admission réelle crée un job permanent : elle exige une recette isolation/rollback distincte avant tout test de production.

Le transport REST est fonctionnellement câblé mais **non activé à l'installation**. Les endpoints de claim, heartbeat, annulation et finalisation métier ne sont pas fournis par Orchestrator 4.3a0. Ne pas présenter ce lot comme une queue scientifique opérationnelle de bout en bout.
