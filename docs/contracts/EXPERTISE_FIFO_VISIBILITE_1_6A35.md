# LAB 1.6a35 — Visibilité des expertises et de la FIFO locale

## Constat et cause

Une expertise `assistant.profile.review` #217 `queued` bloque actuellement PROFILE #218 `queued`. Le consommateur `lab:profile:drain` quitte quand la tête n'est pas `profile`. Le catalogue affichait « Révision IA en attente » sans consultation des jobs d'expertise. Ces faits sont confirmés par les journaux MAIN du 08/11/2026 et le code publié LAB 1.6a33.

## Modification

La projection `LocalQueueVisibility` interroge exclusivement les jobs locaux de `analysis_jobs`. Ordre `created_at, id`, types `profile`, `assistant.profile.review`, `expertise.profile`, états `queued`, `running`, `cancelling`. La file est visible sur le catalogue ; chaque morceau affiche ses expertises actives et sa dernière demande si elle a atteint un état terminal. « Aucune expertise demandée » est distinct de « En attente ». Les jobs Orchestrator historiques sont exclus de cette *file locale*.

## Limites importantes

Cette projection est en lecture seule et n'implémente ni claim, ni lease, ni exécution distante, ni réveil, ni replay. La réalité des jobs #217 et #218 est préservée, mais **leur blocage persiste** jusqu'à mise en service et recette d'un consommateur Assistant contractuellement sûr. Les anciens jobs restent sans migration destructive. La pagination des dernières expertises est bornée à 500 lignes (les jobs actifs n'ont pas cette limite).

## Impacts et restauration

- Avant : expertise absente du catalogue malgré demande enregistrée ; après : visibilité dans la FIFO et par chanson.
- Risque : une seconde lecture DB par affichage de catalogue. SQL borné, pas de mutation, tri stable ; recette sur MAIN exigée.
- Aucune modification : processus, tâche Windows, ports, Caddy, moteurs et modèles, DB/migrations, Orchestrator, PROD.
- Restauration des fichiers contrôlée par manifest SHA256 et sauvegarde sous H:\temp. Aucune purge de jobs.
- Tests : ordre FIFO, exclusion Orchestrator, concordance templates et intégration manuelle sur MAIN.

## Critères d'acceptation

Dans le catalogue, #217 EXPERTISE IA est en position 1, #218 PROFILE position 2 tant que leurs états ne changent pas. Plusieurs demandes sont toutes visibles et identifiées ; le résultat scientifique antérieur demeure consultable. Après claim et finalisation de l'Assistant (futur chantier), les états doivent refléter SQLite, les liens de preuve sont ceux de la révision réellement persistée. Aucun label « révision en attente » ne doit masquer l'absence réelle de demande.
