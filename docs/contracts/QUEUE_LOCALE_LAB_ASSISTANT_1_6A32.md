# Queue locale LAB — PROFILE / Assistant — 1.6a32

Statut : étape LAB locale, sans changement Orchestrator. Ordonnanceur futur déportable, non activé.

## Source de vérité

- `analysis_jobs` SQLite est l'unique file persistante partagée pour les deux types : `profile` et `assistant.profile.review`.
- Admission atomique par transaction `BEGIN IMMEDIATE`, ordre `(created_at, id)`, jobs non terminés préservés.
- Un unique job actif parmi ces deux types. Un exécuteur ne peut revendiquer que la tête de file correspondant à son type.
- `ProfileController` et `PROFILE ALL` utilisent `LocalProfileQueue::enqueue`, sans démarrer Python directement.
- `LocalProfileDrainCommand` démarre le **même** moteur scientifique historique `lab:profile:once`, avec `--job-id` et claim atomique.
- `ProfileAiRequestService::pending/claim/finish` est l'interface existante de l'Assistant ; le worker de l'Assistant n'est pas modifié.
- Job en attente annulable avant claim ; job PROFILE en cours utilise exclusivement le mécanisme d'annulation existant.
- Ne jamais prendre un processus inconnu, ne jamais modifier les jobs `dispatch_owner=orchestrator`.

## États et reprise

- Les jobs sont persistants. Le drain éphémère est réveillé lors des admissions PROFILE, et lors de la fin d'un job Assistant.
- Une commande de reprise explicite `php bin/console lab:profile:drain` est disponible en cas d'arrêt du drain après incident.
- Un job `running` après crash n'est **jamais** repris de force ; un opérateur doit diagnostiquer et réconcilier son état avant reprise, sans double exécution.
- Un job Assistant à la tête de file attend son worker existant. Aucun contournement automatique.
- Priorité aux données et aux résultats historiques : aucune migration SQLite et aucune suppression.

## UI et rafraîchissement

- Par défaut, rafraîchissement ciblé des éléments ; navigation complète uniquement après transition de fin justifiée.
- Cette version préserve le panneau PROFILE R3 ; la finalisation UI R4 demeure un chantier distinct non inclus.

## Tests de recette obligatoire sur MAIN

1. Préflight ZIP, lint PHP, lint Twig, PHPUnit, `git diff --check`, VERSION.
2. PROFILE individuel : admission en `queued`, passage `running`, étapes et sortie `done`.
3. PROFILE ALL : admission d'une seule instance active par chanson, FIFO persistante, aucune réanalyse involontaire de résultats sans action explicite.
4. Assistant : une demande dans la même file, prise par le worker Assistant uniquement, attente quand PROFILE est actif et inversement.
5. Annulation queued/running et reprise drain ; aucun double claim, pas de perte de résultat historique.
6. Arrêt si état incertain ; Git commit/push par le mainteneur uniquement.

## Restrictions

Ne pas modifier Orchestrator, ses API, les moteurs scientifiques, les presets ou la configuration du worker Assistant.
