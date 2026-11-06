# LAB 1.6a8 — Accusé d'échec PROFILE persistant

## Périmètre

Correction ciblée d'une lacune du bridge LAB 1.6a7 : le contrôleur Orchestrator 4.3a6 accepte déjà un reçu signé avec `result=failed` et `artifact_sha256=null`, mais LAB ne l'émettait jamais après retour non nul du worker scientifique. La migration Messenger était incapable de clôturer ces échecs ; le créneau GPU restait occupé dans `global_queue_jobs`.

Cette livraison **ne reprend pas** le bail historique global #1 / LAB #202, ni un autre lease : aucun appel REST, aucun job et aucune base réels ne sont touchés par le préflight ou l'application. La récupération d'un incident déjà survenu demande une attestation indépendante de terminaison des descendants, séparée de ce lot.

## Conduite après un code retour Python non nul

1. Le lanceur Job Object a retourné un code non nul après sa fin de processus. Ne pas assimiler ce cas aux sorties prématurées/attestations impossibles ; ces dernières continuent de retenir le bail.
2. `ProfileExecutionService::failFromJob()` enregistre l'état scientifique `error` et le code d'erreur dans LAB ; aucune écriture directe dans `analysis_jobs.status`, protégée par trigger.
3. `GlobalQueuePersistedReceipt::failedProfile()` exige `admitted`, propriétaire Orchestrator, identités de job concordantes et `scientific_runs.state=error` avant signature HMAC (aucune preuve d'artefact fictive).
4. Le bridge envoie le reçu à l'endpoint `finish` existant ; Orchestrator vérifie lease, génération, HMAC et passe le job global à `failed` atomiquement. Si l'attestation ou le POST échoue, le bail n'est pas libéré à l'aveugle. Le message Messenger peut signaler l'incident ; aucune nouvelle exécution du même job n'est autorisée sans récupération attestée.
5. Le handler accepte `failed` comme état de traitement traité, sans le présenter comme `completed`. Le statut scientifique demeure `error`.

## Étude d'impact

| Avant | Après | Risque | Contrôle | Retour arrière |
|---|---|---|---|---|
| Code retour non nul → exception, lease `running` | `error` LAB attesté puis `failed` global | Finalisation indue | Check des identités, HMAC, lease+génération, état scientifique persisté | Sauvegarde des seuls fichiers sources via installateur ; données conservées |
| Rejet Messenger après échec | Handler accepte la conclusion `failed` | Reconsommation | Transport exige le run `queued` ; `error` n'est plus éligible | Restaurer le code après arrêt volontaire du worker |
| Job #202 en cours d'incident | Intact | Récupération non sûre | Aucun traitement automatique des anciens jobs | Sans objet |
| Autres moteurs, modèles, presets, DEV/PROD, Assistant | Inchangés | Régression transversale | PHPUnit intégral LAB ; aucune modification dans autres repos | Sans objet |

## Limites à traiter dans les lots suivants

- Les erreurs d'attestation/transport *avant* retour enfant ne sont pas récupérées automatiquement.
- Pas encore de projection `JobRegistry.attempts` dans la queue globale ; RC et statut de finalisation ne sont pas encore exposés dans l'UI Orchestrator.
- Le transport Messenger reste local à LAB, non conforme à la topologie cible d'Orchestrator central ; sa migration doit être séparée et prouvée.
- Le code produit ne doit pas dépendre de `H:/temp` : un usage déjà existant du bridge pour l'enveloppe du worker demande une convergence indépendante vers un emplacement durable si ces preuves doivent survivre à une purge.

## Recette

Suite PHP ciblée + complète, lint PHP, lint Symfony/Twig, intégration REST synthétique sur SQLite temporaire, puis **une seule analyse réelle de validation sur un nouveau job** après résolution contrôlée du lease historique et autorisation d'exécution. Une réussite de PHPUnit n'est pas une réussite opérationnelle. Aucun claim sur #181/#182/#185/#202.
