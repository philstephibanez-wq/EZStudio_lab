# LAB 1.6a28 — PROFILE one-shot : étapes observées et archives FIFO

## Constat et preuve

- Le run Aline #183 et le job LAB #208 ont terminé par `PROFILE_ONCE_COMPLETED`.
- Le comparateur des runs #153 / #183 a confirmé `schema`, `characteristics` et `tagging` identiques. Les différences d'empreintes, de device et de mesures numériques internes restent tracées.
- Les anciens jobs FIFO #206/#207 demeurent dans SQLite. Ils ne doivent pas être affichés comme une exécution one-shot active.
- Le runner historique écrit déjà `percent`, `message`, `updated_at` dans `var/tmp/jobs/<id>/progress.json`; le module courant peut donc être affiché **sans changer le moteur**.

## Solution et architecture

- `CatalogService` conserve le dernier run PROFILE `done` et marque en lecture seule `legacy_fifo` les tentatives `queued/running` dont le job conserve `dispatch_owner=orchestrator`. Les autres phases ne changent pas. Aucune mutation de la base, aucune suppression d'historique.
- `/profile/song/{id}/oneshot-status` (GET) lit **le dernier job PROFILE local** et le message du fichier de progression correspondant au job ID dérivé de la base ; résultat JSON borné et sans chemin source ni secret.
- La page PROFILE affiche pendant l'exécution : numéro de job/run, progression effective, nom du module courant et journal en mémoire des transitions observées dans cette session de navigateur. L'absence d'observation est explicite et ne vaut pas démarrage ou achèvement. Le `after_job` capturé avant le spawn empêche de présenter un ancien run comme une nouvelle exécution.
- Après fin, la page PROFILE montre la liste des modules du `module_registry` **persisté** dans le résultat scientifique et leurs durées disponibles, sans reconstituer une progression passée fictive.
- L'ancien affichage `Dernier essai : en attente` n'est pas une preuve de traitement encore actif si la tentative dépendait de la FIFO retirée. Les anciens jobs restent dans l'historique.

## Impacts

| Domaine | Avant | Après | Risque/prévention |
|---|---|---|---|
| LAB Web | Pourcentage et bannière sommaires | Étape réelle et progression observable | API locale en lecture seule, texte échappé, aucune commande Python/HTTP depuis GET |
| LAB Catalogue | Ancien job FIFO déclaré actif | Badge neutre « ancienne tentative » | `dispatch_owner` vérifié, aucune écriture DB, dernier succès conservé |
| LAB scientifique | Worker historique direct validé | Inchangé | Aucun changement Python ou du service one-shot |
| Autres phases | Affichage existant | Inchangé | Branche `item=profile` uniquement |
| Orchestrator/PROD | Hors périmètre | Inchangés | Aucun fichier ni appel vers Orchestrator |
| Exploitation | Refresh manuel pour connaître l'étape | Poll GET toutes les 5 s, borné à 240 lectures | Pas de service permanent, ni tâche Windows, ni console supplémentaire |
| Retour arrière | Version 1.6a27 | Restauration des 5 fichiers depuis H:\temp | Pas de rollback SQLite nécessaire |

## Contrôles

1. Préflight LAB version `1.6a27`, blobs GitHub inchangés sur les deux fichiers publiés, ancres exactes R1C sur les fichiers non encore poussés, UTF-8 sans BOM, fichiers nouveaux absents.
2. Sauvegarde des cinq fichiers modifiés sous `H:\temp`, installation, vérification d'empreintes et ancres, aucun lancement de job.
3. `php -l` (deux fichiers PHP), `php bin/console lint:container`, `php bin/console lint:twig templates`, `php vendor/bin/phpunit --no-progress`, `git diff --check`.
4. Recette navigateur : catalogue Aline affiche le résultat #183; les jobs FIFO antérieurs portent un badge neutre, sans bouton annuler actif; l'écran de résultats #183 affiche les modules persistés.
5. Recette dynamique facultative sur une **nouvelle demande utilisateur explicite** : bouton Web one-shot, vérifier PID/processus Python et étapes affichées par comparaison avec les logs, puis résultat `done`. Ne pas lancer un calcul GPU pour la simple recette statique.

## Statut de preuve

- Relecture des sources GitHub et contrat de développement réalisée ; contrôles du ZIP local et des fichiers sources modifiés à réaliser sur MAIN.
- Les tests Twig/Symfony complets et le comportement navigateur sur MAIN ne sont **pas** prétendus validés avant leur exécution.
- Le passage `running` ne prouve pas à lui seul le démarrage Python ; seul le `message` du runner, l'identité du processus et sa sortie constituent des preuves distinctes.

## Publication

Aucun commit/push par l'assistant. Message suggéré après recette :

`[1.6a28] fix(profile): rendre visibles les étapes one-shot et distinguer les archives FIFO`

Corps : Contexte / Modifications / Validation / Impact / Limites-risques.
