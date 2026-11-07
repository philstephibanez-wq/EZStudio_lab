# LAB autonome — 1.6a21

## Objet

Restaurer la **création locale des demandes PROFILE** à partir du flux UI existant, sans nouvelle admission dans la queue Orchestrator. Réutiliser exclusivement le moteur historique `analysis/worker_entrypoint.py`, `ProfileExecutionService` et la base LAB. Pas de changement des modèles, presets ou calculs musicaux.

## Étude d'impact

| Composant | Avant | Après | Risque / prévention / rollback |
|---|---|---|---|
| Nouveaux jobs PROFILE | `dispatch_owner=orchestrator` | `dispatch_owner=lab` via `EZSTUDIO_GLOBAL_QUEUE_ADMISSION=0` | Environnement PHP externe `=1` prioritaire : préflight le refuse quand visible ; contrôler le runtime réel avant nouvelle demande. Restauration des deux flags avec l'installateur. |
| Bridge global LAB | Autorisé | `EZSTUDIO_GLOBAL_QUEUE_EXECUTOR=0` | Une exécution de #204/#205 ne doit **pas** redémarrer pendant le retour à l'autonomie. Les anciennes demandes gardent leur propriétaire. |
| Exécution locale | Non raccordée automatiquement | Commande `lab:profile:execute-local <job-id>` explicite, `--apply` pour une seule exécution | Pas de consommateur permanent fourni : ne pas qualifier l'autonomie complète d'opérationnelle avant attestation réelle. |
| Global #9 / LAB #204 | `running`, génération 1, lease | Strictement inchangé | L'exécuteur local refuse toute exécution tant qu'un job global reste `running`. Pas de release implicite, de mutation de propriétaire, ni de reprise. |
| Global #10 / LAB #205 | `queued` | Strictement inchangé | Aucune admission ni relance. |
| Données, stockage et GPU | inchangés | inchangés | Aucune migration/suppression. Préflight et installation ne chargent aucun modèle, n'exécutent aucun job. |
| Orchestrator, Assistant, PROD, services | inchangés | inchangés | Aucune opération de processus ou redémarrage. |

## Chaîne d'exécution

Le mode sans `--apply` lit le job et s'assure qu'il est `profile`, `queued`, `dispatch_owner=lab`. Le mode `--apply` vérifie les flags locaux, la file globale, le Python scientifique et le fichier audio ; verrouille un seul worker local, réattribue atomiquement **uniquement** le job local sélectionné à `running`, exécute le worker Python canonique puis persiste les résultats avec `ProfileExecutionService`. Il n'ouvre jamais #204/#205 car `dispatch_owner=orchestrator`.

## Exclusion critique

Cette livraison ne réconcilie pas la réservation orpheline #9. Elle ne bascule jamais les jobs #204/#205 en mode local et n'offre pas de redémarrage automatique. Le LAB ne doit pas être déclaré opérationnel pour les **analyses scientifiques** tant que le global #9 est `running`. Toute récupération du lease exige identité du processus et attestation d'arrêt, puis protocole canonique de récupération.

## Recette / publication

1. `python INSTALL.py preflight` : lecture seule.
2. `python INSTALL.py apply` : changement version, ajout commande/test/doc et deux flags `.env.local` seulement ; sauvegarde des anciennes valeurs des flags, jamais des secrets.
3. `cd H:\EZStudio_lab ; composer test` ; `php bin\console lint:container` ; `php bin\console lint:twig templates` ; `git diff --check`.
4. `php bin\console lab:profile:execute-local 204` : **refus attendu** `LOCAL_PROFILE_OWNER_OR_STATE_REFUSED`, sans écriture.
5. Ne pas utiliser `--apply` tant que #9 reste `running` ou sans recette autorisée.
6. Rollback selon `ROLLBACK=` affiché, uniquement si les fichiers sont encore identiques à ceux livrés.
7. Publication seulement après recette opérationnelle LAB et vérification du fonctionnement visuel.

## Versions / preuves

- LAB avant installation : `1.6a20`, attendu sur MAIN ; après : `1.6a21`.
- LAB `1.4a` / rapports scientifiques `run-000165`, `run-000166`, `run-000167` : analyses `done` le 26/10/2026, moteur `r3b31`. Cela n'atteste pas le fonctionnement du transport global.
- Orchestrator : `4.3a17`, non modifié par la présente livraison.

## Message de commit proposé (mainteneur uniquement)

`[v1.6a21] fix(profile): isoler la création locale de PROFILE et ajouter une exécution locale explicitement contrôlée`

Contexte : la migration vers la queue globale bloque les analyses.

Modifications : flags locaux, commande LAB, tests et contrat ; aucun moteur scientifique modifié.

Validation : tests statiques exécutés dans l'environnement de préparation ; recette sur MAIN et fonctionnement GPU restant à valider.

Impact : LAB uniquement ; aucune base, ancien job ni PROD modifié.

Limites / risques : #204/#205 toujours sous propriété Orchestrator, réservation #9 non réconciliée ; aucune supervision permanente.
