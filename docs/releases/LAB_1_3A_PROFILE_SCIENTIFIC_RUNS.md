# EZStudio_lab 1.3a — PROFILE et Scientific Runs

## Commit de référence

`9d3fb38` — `refactor(lab): consolidate profile presets and scientific run terminology`

Ce document complète la traçabilité du commit existant sans réécrire son historique Git.

## Objectif et périmètre

Consolider le runtime déclaratif PROFILE R3B35C et harmoniser la terminologie active autour des **Presets**, **Modules** et **Scientific Runs**. Les changements comprennent les migrations R11 à R14 et les adaptations de contrats associées.

## Modifications livrées

- Renommage de `DnaController` vers `ScientificRunController`, des routes scientifiques et des templates associés.
- Renommage des services d'exécution PROFILE, STEMS et CHORDS et du service de synchronisation STEMS ; adaptation de leurs consommateurs.
- Suppression des classes et alias obsolètes, dont les anciens alias Python de terminologie.
- Harmonisation des références PHP et Twig et des tests de contrats correspondants.
- Consolidation du chargement déclaratif PROFILE et de la configuration CLAP R3B35C.
- Mise à jour de `VERSION` vers LAB 1.3a.

## Validations réalisées

- R11 à R14 : préflights et validations des migrations terminologiques.
- R15 : **7/7 contrats réussis** ; vérifications syntaxiques PHP et Python réussies.
- Twig : **21/21** templates validés ; conteneur Symfony validé.
- Git : staging sélectif, `git diff --cached --check` et exclusion des fichiers sensibles vérifiés.
- R16 : pas d'instruction SQL mutatrice exécutée directement identifiée dans les sept scripts inspectés ; ce contrôle statique n'exclut pas des effets indirects.
- R17 : changements de `benchmark.sqlite` constatés pendant une observation sans exécution de tests ; processus responsable non identifié.

## Garanties et exclusions du commit de référence

- Aucune régénération audio ni inférence GPU requise pour les migrations terminologiques.
- Aucune modification intentionnelle des algorithmes scientifiques STEMS, CHORDS/N ou LYRICS ; équivalence scientifique complète non démontrée.
- Aucun changement sur le dépôt EZScore.
- `presets/history/` et `presets/assistant/` non intégrés au commit de référence.
- SQLite, modèles IA, caches, sauvegardes, secrets et fichiers temporaires exclus.
- Les correctifs de supervision R20/R21, relevant du dépôt EZS_orchestrator, sont hors périmètre.

## Travaux distincts restant ouverts

La révision de PROFILE par l'ASSISTANT IA, sa persistance et sa réutilisation dans LAB n'ont pas encore été validées de bout en bout. L'IA locale Qwen est disponible sur l'installation distante, mais l'activation du fournisseur dans le worker PROFILE et la procédure ciblée de redémarrage restent à sécuriser.

## Traçabilité Git

Ce document accompagne le commit `9d3fb38` déjà publié et ne nécessite **ni amend ni force-push**. Sa publication doit être un commit documentaire distinct, limité à ce fichier.
