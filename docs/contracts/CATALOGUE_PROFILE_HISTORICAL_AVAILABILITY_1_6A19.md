# LAB 1.6a19 — Disponibilité historique PROFILE et statut de tentative

## Cause vérifiée

Le catalogue choisissait exclusivement le run scientifique le plus récent (`ORDER BY id DESC LIMIT 1`) et utilisait son état pour colorer le bouton PROFILE. Une tentative récente annulée ou échouée masquait le dernier run `done`, sa version et sa révision IA.

## Contrat de lecture

- `profile` expose en priorité le **dernier run scientifique `done`** lorsqu'il existe ; il conserve son `run_id`, sa version et sa révision IA liée au même run.
- `profile.latest_attempt` décrit séparément la tentative scientifique la plus récente si celle-ci est différente du run disponible. Ne pas la transformer en succès.
- Sans run `done`, afficher l'état réel de la dernière tentative ; ne pas produire de faux indicateur « Prêt ».
- Les autres phases (STEMS, CHORDS, LYRICS) conservent leurs règles existantes.
- Les tentatives annulées restent gris neutre, les échecs rouges et les traitements en cours distincts.
- Le cache-busting JavaScript dépend désormais de `lab_version` ; le test de catalogue ne doit jamais figer une version de livraison.

## Étude d'impacts

| Domaine | Avant | Après | Risque et protection |
|---|---|---|---|
| Catalogue | Dernière tentative masque le succès | Dernier succès conservé, tentative distincte | Tests SQLite isolés, aucun UPDATE métier |
| Révisions IA | Calculées sur le dernier run visible | Calculées sur le dernier run `done` visible | Ne jamais associer une révision d'un autre run |
| Jobs | État local parfois désynchronisé | Aucun changement de protocole dans cette livraison | Ne pas rejouer ni réconcilier automatiquement |
| Orchestrator | Entrées anciennes dans la queue | Inchangé | Chantier séparé, aucune suppression SQLite |
| Déploiement | LAB 1.6a18 installé | LAB 1.6a19 | Sauvegarde ciblée et rollback hors dépôt |
| Navigateur | Badges erreur jaunes | Badges erreur rouges | CSS isolé, Twig lint requis |

## Recette

1. Préflight à dépôt LAB `main`, VERSION 1.6a18, pas de fichiers externes modifiés ou indexés.
2. Installation avec sauvegarde sous H:\temp ; aucun accès SQLite, worker ou GPU.
3. PHPUnit ciblé `CatalogueProfileHistoricalAvailability1_6A19Test.php`, puis PHPUnit complet ; Symfony `lint:container`, `lint:twig`; pytest PROFILE si ses dépendances sont disponibles.
4. Contrôle Git, encodage strict UTF-8 sans BOM, non-régression, sauvegarde et documentation du commit.
5. Recette navigateur : chanson dotée d'un ancien PROFILE `done` + tentative `cancelled` ou `failed`; bouton vert et révision du run sélectionné. Ne pas lancer de job pour cette recette.

## Limitations

Cette livraison ne corrige pas la file opérationnelle Orchestrator et ne prouve pas une exécution scientifique réelle. Les changements de lecture ne modifient aucun historique stocké. Il n'est pas acceptable de déclarer le chantier complet sur la seule base de tests unitaires.
