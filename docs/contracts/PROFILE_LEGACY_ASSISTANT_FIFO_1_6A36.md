# Contrat — Raccordement Assistant historique / FIFO LAB — v1.6a36

## 1. Constat et cause racine

Le client Python historique de l'Assistant (branche GitHub `assistant-main`) utilise :
- `GET /internal/analysis/desktop/profile/reviews/queue` ;
- `POST /internal/analysis/desktop/profile/reviews`.

Dans LAB 1.6a35, ces routes sont gérées par `ProfileExternalReviewService`, alors que les jobs `assistant.profile.review` sont stockés dans la FIFO unique `analysis_jobs` et gérés séparément par `ProfileAiRequestService`. Une révision enregistrée par le chemin historique ne clôturait pas le job canonique correspondant ; le drain PROFILE ne doit pas passer devant une tête EXPERTISE.

Diagnostic MAIN du 09/11/2026 : #217 `assistant.profile.review` run 191, #218 `profile` run 192 et #219 `assistant.profile.review` run 186 en attente, sans job LAB actif ; maintenance désactivée ; `PROFILE_DRAIN_IDLE`. Le port IA local 8720 était inaccessible, sans établir la configuration effective du consommateur historique.

## 2. Périmètre et décision

**Un seul dépôt : `EZStudio_lab`**. Assistant, Orchestrator, moteurs, presets, catalogue et fichiers scientifiques inchangés. Aucune nouvelle file, aucun worker parallèle, aucune tâche Windows, aucun changement de configuration ou de secret.

LAB fournit désormais au client historique uniquement sa propre **tête FIFO éligible** ; il construit la réponse de compatibilité à partir de `ProfileExternalReviewService::assertionsForRun` et `LegacyProfileFinalProjection`, sans transmettre les métriques brutes, les paramètres de moteur, le preset ou les diagnostics scientifiques.

La projection conserve tous les `final_assertions` dynamiques et leur identité. Pour ne pas casser le client historique, le champ technique `diagnostics.profile_view` subsiste, mais ne contient qu'une **représentation synthétique des valeurs finales publiées**, jamais des observations scientifiques privées. Les clés `metrics` et `config` sont vides.

Lorsque l'Assistant historique publie une révision `assistant-auto` au statut `completed`, avec les sources exigées par le service existant, LAB exécute **une transaction SQLite unique** : garde maintenance / propriétaire / FIFO / exclusivité, `queued → running`, persistance de la révision, `running → completed`, `COMMIT`. Toute erreur provoque `ROLLBACK` de la révision et des statuts. Après `COMMIT`, le réveil du drain local est tenté pour dépiler la tête suivante. Les `draft` et les révisions manuelles suivent les comportements existants.

## 3. Étude d'impacts (§5.1 du contrat canonique)

| Domaine | Avant | Après, risque et prévention | Preuve ou restauration |
|---|---|---|---|
| FIFO | Route historique indépendante du job canonique | Garde `LocalProfileQueue::headSql/activeSql` et propriétaire LAB ; aucun saut de #217 | Tests #217/#218/#219 ; restauration |
| SQLite | Révision et job non synchronisés | Un seul `BEGIN IMMEDIATE`, persistance et clôture atomiques ; refus si maintenance/compétition | Tests de rollback, refus hors tête ; restauration du code sans toucher aux jobs |
| Confidentialité | Route historique exposait métriques / diagnostics | Projection de seules assertions finales ; aucune donnée scientifique privée transmise dans cette route | Test « sentinel » anti-fuite, inspection HTTP réelle restante |
| Compatibilité | Client historique consommait titre/artiste/run et `profile_view` | Format conservé pour ses champs utilisés ; tous les autres items finaux restent dans `final_assertions` | Tests Python/HTTP réels à exécuter sur MAIN |
| Qualité métier | Le client historique ne couvre pas forcément les items dynamiques | Ne jamais inventer BON/FAUX ; une absence de révision vérifiée laisse la tête en attente | Recette réelle item par item obligatoire avant déclaration de succès |
| Exploitation | Consommateur historique non observé actif au diagnostic | Aucune création/activation de processus par cette livraison ; absence de consommateur reste un obstacle possible | État MAIN et résultat réel requis ; aucune action PROD |
| Bootstrap / ports / ressources | Aucun changement demandé | Pas de démarrage, watchdog, fenêtre console, GPU ou service additionnel | Aucun impact direct ; audit de fonctionnement réel restant |
| Non-régression | 37 tests LAB / 285 assertions réussis avant changement | Tests ciblés et suite complète à réexécuter ; version 1.6a36, SHA préflight des deux services | Backups sous `H:\temp`, restauration par SHA sans suppression de données |

## 4. Tests et validation

**En environnement de préparation** : syntaxe PHP 8.4 des nouveaux fichiers ; test d'exécution réel de la projection des assertions finales ; transformateur et `bundle-check` ; simulation installation / vérification / restauration / second préflight sous un faux dépôt, avec CRLF. Aucun accès à la base MAIN ni exécution du moteur IA.

**Sur MAIN restent obligatoires** : préflight source / version ; application sauvegardée ; `php -l` pour les trois fichiers PHP ; `lint:container`, `lint:twig`, `composer validate`, PHPUnit complet, `git diff --check`, vérification UTF-8 sans BOM et de l'interface ; tests HTTP des routes avec authentification existante et verdicts réellement persistés ; contrôle du traitement #217, reprise #218, puis éligibilité #219. Ces validations **ne sont pas encore réussies** au stade du ZIP.

**Limites** : la disponibilité du processus historique et du modèle IA sur MAIN n'est pas démontrée ; la capacité du client historique à examiner et justifier *chaque* assertion dynamique reste à vérifier. Cette correction restaure le raccordement LAB mais ne prouve pas, seule, l'expertise de bout en bout.

## 5. Déploiement et publication

Préflight strict sur `main` LAB version 1.6a35, blob SHA attendu des deux services ; installation sans job ni migration ; sauvegarde de chaque fichier remplacé, création exclusive des nouveaux ; restauration refusée si les fichiers ont changé après installation. Aucun commit/push par l'assistant.

Commit suggéré après recette : `[v1.6a36] fix(profile): raccorder la révision historique à la FIFO LAB`. Corps : Contexte, Modifications, Validation (réalisées / restantes), Impact et Limites.
