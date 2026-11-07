# LAB 1.6a18 - Reconciliation d'un etat global terminal

## Anomalie documentee

Orchestrator job #2 / LAB job #203 / scientific run #178 : l'autorite globale a `failed`, LAB conserve `analysis_jobs.status=queued` mais `scientific_runs.state=error`. Le contrat antérieur refuse la reconciliation si le scientific run n'est plus `queued`.

## Decision

La confirmation par le service `GlobalQueueTerminalSync` de l'identite exacte et de l'etat terminal chez Orchestrator reste obligatoire. `Database::reconcileGlobalTerminal` accepte une execution scientifique `queued` ou **deja dans l'etat terminal correspondant** : `failed -> error`, `cancelled -> cancelled`. Elle ne reinterprete jamais un resultat scientifique et conserve `finished_at` quand celui-ci est deja terminal.

Les recus SQLite, la transaction `BEGIN IMMEDIATE`, les fences d'ownership, l'absence de replay et les refus sur identite/etat contradictoires restent inchanges.

## Portee et risques

Aucun schema, moteur scientifique, preset, jeton, processus, GPU ou donnees MAIN n'est modifie par l'installation. Le correctif touche uniquement `Database.php`, `VERSION`, un test PHPUnit et ce contrat. Le bouton Annuler un job global **deja echoue** demandera une reconciliation vers `failed`, **pas** une annulation retroactive. Aucune synchronisation automatique sur la simple lecture du catalogue n'est ajoutee dans ce lot.

## Recette

Tests sur SQLite temporaire hors depot: etat `failed/error` deja terminal avec timestamp conserve; `cancelled/cancelled`; `queued` scientifique initial; conflit terminal rejete sans mutation; identite globale fausse rejetee. Ensuite PHPUnit LAB complet, lint Symfony/Twig, PHPUnit Orchestrator et controles d'encodage et Git sur MAIN. Les tests automatiques ne prouvent pas l'annulation HTTP ni un lancement scientifique reel.

## Suites hors perimetre

La file globale Orchestrator actuelle comporte six demandes historiques `queued` et deux echecs. Le nettoyage de sa vue V1 et la reconciliation de ses admissions historiques devront etre traites **dans une livraison Orchestrator distincte**, sans supprimer les preuves ni executer automatiquement les jobs. La projection des etats sur le catalogue LAB est egalement une evolution distincte.
