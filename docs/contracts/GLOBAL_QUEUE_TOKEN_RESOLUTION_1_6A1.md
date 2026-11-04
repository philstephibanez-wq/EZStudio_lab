# LAB 1.6a1 — résolution du jeton d'admission

`GlobalQueueAdmission::owner()` et `publish()` lisent désormais le même jeton depuis `getenv()`, puis `$_SERVER`, puis `$_ENV`. Ce changement traite le cas Symfony Dotenv qui ne renseigne pas `getenv()` par défaut. La valeur n'est jamais exposée en log ni dans une réponse HTTP.

`EZSTUDIO_GLOBAL_QUEUE_ADMISSION` demeure une activation explicite : absent ou autre que `1`, les futures demandes continuent leur chemin LAB. Le provisionnement du jeton ne bascule pas ce flag. Les jobs historiques et l'outbox ne sont pas modifiés par l'installation.

La recette positive HTTP est réservée à un lot isolé ultérieur : Orchestrator 4.3a1 ne sait toujours pas finaliser/exécuter la queue globale. Aucun job scientifique ni worker ne doit être lancé lors de ce correctif.
