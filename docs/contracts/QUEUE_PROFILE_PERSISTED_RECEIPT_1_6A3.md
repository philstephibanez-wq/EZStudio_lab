# EZStudio_lab 1.6a3 — Reçu PROFILE après persistance (composant dormant)

## Contexte et sources

LAB v1.6a2 `06a7f16002ae5b12f26f47e4f2966b5240984215` ; Orchestrator v4.3a4 `6e3b4f1857177269e2a7ddcd748d66e92602175d`. Contrat canonique, AGENTS, `ProfileExecutionService`, `AnalysisRunRegistry`, `GlobalQueueAdmission`, `Database` et `QueueResultReceipt` lus avant conception.

Orchestrator refuse `finish` sans reçu HMAC émis par LAB. Un fichier `profile.json` existant seul **ne prouve pas** que LAB a enregistré ses résultats. Le déclencheur `trg_global_queue_admission_fence_status` protège le statut des jobs globaux ; il reste intact.

## Fonction introduite et portée

`App\Service\GlobalQueuePersistedReceipt::completedProfile(int $labJobId,int $globalJobId,int $claimGeneration)` est un composant **dormant, non exposé en HTTP, non câblé à un worker et sans écriture**. Il n'est destiné qu'à un futur lanceur LAB de confiance, après contrôle de son lease et de l'identité du processus par Orchestrator. Il produit uniquement des reçus `completed` ; les reçus `failed`, l'exécution, le protocole de transition d'état LAB et la récupération réseau restent ultérieurs.

Conditions simultanées (refus fermé si l'une échoue) :

- Job LAB existant, `kind=profile`, `dispatch_owner=orchestrator`, statut encore `queued` conformément au trigger ; entrée d'outbox `admitted`, identifiant global correspondant exactement.
- `scientific_runs` rattaché au même job et à la même chanson : `item=profile`, `state=done`, `finished_at` renseigné.
- `request_json` cohérent avec le run, le hash audio de la chanson et `output_contract=ezstudio.profile.v1`.
- Un seul artefact enregistré pour le run avec `role=profile_json`, chemin identique au chemin demandé, empreinte SHA-256 correcte ; fichier présent, lisible, JSON valide et schéma PROFILE attendu.
- Clé `EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY` valide (32 à 256 octets), récupérée seulement dans l'environnement natif ou Symfony ; aucune clé fournie dans le code, le ZIP ou Git.

La réponse est **exactement** le sous-objet `receipt` prévu par Orchestrator 4.3a4 : `schema`, `producer`, `external_id`, `job_id`, `claim_generation`, `result`, `artifact_sha256`, `signature`. La signature HMAC-SHA256 porte sur les sept lignes ASCII canoniques, dans l'ordre prévu, séparées par LF sans LF final. Le reçu est signé **après** vérification des données durables : aucune affirmation de succès n'est dérivée du statut technique de la queue.

Les arguments `globalJobId` et `claimGeneration` devront provenir d'un lease attesté par le futur exécuteur ; ce service ne confirme pas lui-même le lease Orchestrator. **Ne pas exposer publiquement le service ou fournir cette clé à un utilisateur / client HTTP.** Le reçu n'est pas une autorisation autonome pour réclamer un job.

## Étude d'impact

| Frontière | Avant | Après | Risque et garde-fou | Retour arrière |
|---|---|---|---|---|
| Science PROFILE | `ProfileExecutionService::completeFromJob` persiste le run et l'artefact | inchangé | lecture seule, aucune altération du moteur ou preset | retirer le service dormant |
| SQLite LAB | job global fenced, outbox d'admission | même schéma et trigger, aucune écriture de la classe | tests SQLite synthétiques, refus d'un run absent ou non `done` | aucun rollback DB |
| Orchestrator | récepteur HMAC 4.3a4 dormant | inchangé ; même canonisation | test de vecteur HMAC/identité ; pas de requête REST | aucun impact |
| MAIN/Windows | serveur FrankenPHP partagé, workers inchangés | aucune tâche, service, port, process ni fenêtre supplémentaire | installateur n'appelle aucun endpoint ni moteur | restaurer VERSION et supprimer les trois fichiers |
| Jobs historiques | sous autorité LAB ; #201 affiché en attente | aucun traitement et aucune migration | service non invoqué par les routes | aucun impact |
| Secrets | pas de clé LAB configurée par ce correctif | aucun provisionnement | fail-closed ; ne rien journaliser | aucun retrait de secret requis |

## Bloqueurs avant analyse PROFILE réelle

1. LAB n'a pas encore de chemin autorisé pour la **transition d'état globale** `queued → running → completed/failed` sous fence SQLite, ni la gestion des échecs et la récupération idempotente.
2. Aucun endpoint interne authentifié ne transmet ce reçu à un exécuteur. Le signer dormant n'est pas une API exploitable.
3. L'exécuteur supervisé, les enfants de processus, la preuve d'arrêt, les leases et l'ack de la réponse Orchestrator restent à intégrer avec des tests de bout en bout.
4. Ni `EZSTUDIO_GLOBAL_QUEUE_ADMISSION=1` ni `EZS_QUEUE_EXECUTOR_API=1` ne doivent être activés par ce lot. Aucune consommation de #201 ou migration des historiques.

## Recette obligatoire

Hors MAIN : syntaxe des scripts PHP, UTF-8 sans BOM, intégrité ZIP, essai de signature canonique indépendant. Sur MAIN : `php vendor/bin/phpunit tests/Unit/GlobalQueuePersistedReceipt1_6A3Test.php --no-progress --fail-on-warning`, suite complète, `php bin/console lint:container`, `git diff --check`, version/branche/statut, vérification que les gates restent désactivées. Les tests utilisent une base et un artefact synthétiques sous `H:\temp` et ne doivent jamais ouvrir la base métier. Aucun changement de DB ni de service pendant l'installation.

Aucun succès opérationnel de bout en bout n'est revendiqué par cette livraison.
