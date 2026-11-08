# LAB 1.6a29 — PROFILE one-shot : progression persistante et annulation ciblée

## Constat (preuve MAIN)

- LAB 1.6a28 : PHPUnit 25 tests / 201 assertions ; Symfony et Twig OK.
- Aline #183 et Supertramp #184 : runs `done`, douze modules visibles depuis leur `module_registry` persisté.
- Sur Supertramp, un bref fragment de progression apparaît puis disparaît malgré la poursuite du calcul ; l'annulation n'agit pas.
- Les anciennes entrées FIFO restent conservées comme archives, et ne doivent pas redevenir annulables.

## Causes

1. Le panneau one-shot R1B était conditionné à `oneshot=started` ou au run sélectionné, et l'ancien poller PROFILE pouvait provoquer des rechargements. La navigation depuis le catalogue ne satisfaisait pas toujours ces conditions.
2. La route d'annulation existante cible la file historique et non le processus démarré en one-shot. Le CLI ne surveillait aucune demande d'annulation.
3. Le worker Python `worker_entrypoint.py` possède son propre enfant `subprocess.Popen` pour `runner.py` ; arrêter uniquement son parent laisserait un enfant potentiellement actif.

## Décision / avant-après

| Domaine | Avant | Après | Prévention / validation |
|---|---|---|---|
| UX | panneau conditionné par URL ; rechargement hérité | panneau universel par chanson, visible selon état LAB réellement actif ; poll 2 s sans reload | test Twig, recette navigateur |
| Progression | étapes volatiles seulement, disparaissent au changement de page | stage courant lu dans `progress.json`; modules terminés issus du run stocké | aucune étape simulée |
| Annulation | aucune commande one-shot | POST même origine, valide owner `lab`, phase `profile`, contexte de job réel ; DB `cancelling` | refus 404/409 pour jobs archivés et terminés |
| Processus | un wrapper Python lançant `runner.py` | signal coopératif du CLI ; le wrapper termine **son propre** Popen child, puis attend sa fin | aucun kill par nom/PID global, aucun `taskkill` |
| Persistance | possible succès après demande d'annulation | barrière terminale : `markAnalysisJobCancelled` avant `completeFromJob` si état `cancelling` | test contrat, recette arrêt pendant une analyse explicitement autorisée |
| Runtime | un job propriétaire et verrou one-shot | pas de nouvelle file ; chemins sous `var/tmp/profile-once/<job_id>` ; journaux inchangés | aucune modification orchestrator |
| Données | historiques conservés | aucune migration / suppression ; installation code seulement | rollback code sans rollback DB |

## Limites et sécurité

- La demande d'annulation est **asynchrone** : le HTTP 202 n'atteste pas l'arrêt effectif. Seul l'état `cancelled` enregistré après l'arrêt du processus attesté confirme l'annulation.
- `worker_entrypoint.py` arrête son enfant direct `runner.py`. Si un modèle crée des descendants externes persistants, leur arrêt n'est **pas prouvé** : aucune recherche ou terminaison globale n'est autorisée. Recette Windows sous observation obligatoire avant validation complète.
- Une demande déposée après le début de l'enregistrement final peut arriver trop tard : un run déjà terminé doit rester `done`, non réécrit rétroactivement.
- Le panneau observe le plus récent job one-shot par chanson. Il n'établit pas la liste exhaustive d'autres runs actifs ; le verrou one-shot interdit normalement leur coexistence.
- Le fichier `progress.json` provient du worker ; le serveur ne lit pas un chemin arbitraire transmis par HTTP.

## Application contrôlée

- Installer uniquement sur LAB `1.6a28`, préflight sans écriture, sauvegarde de chaque fichier avant application, `verify`, et `restore` depuis le chemin exact de BACKUP.
- Aucun worker ne doit être en cours durant le déploiement. Contrôle sur MAIN des jobs/processus obligatoires avant `apply` ; l'installateur ne lance ni ne stoppe de processus.
- PHP / Twig / PHPUnit sur MAIN et test de syntaxe Python, `git diff --check` et UTF-8 sans BOM.
- Recette navigateur : lancement explicite d'un nouveau one-shot sur une chanson choisie, observer les noms de modules et la barre durant toute l'analyse, visiter le catalogue puis revenir sans perte du panneau ; demande d'annulation sur une **autre exécution de test**, attester `cancelling → cancelled`, absence de `done` et absence de runner orphelin.
- Pas de commit/push automatisé. Aucune modification dans `EZS_orchestrator`, EZScore DEV ou PROD.
