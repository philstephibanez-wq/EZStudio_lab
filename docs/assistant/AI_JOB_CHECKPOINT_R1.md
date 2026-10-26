# Checkpoints Assistant 1.4a(27)

Journal de checkpoints par job canonique, GET/POST authentifié sur `/internal/analysis/desktop/profile/ai-requests/{job_id}/checkpoints`. Persistance idempotente sous SQLite, liste fermée de champs (phase, outcome, compteurs, code d'erreur). Aucun changement de statut du job, aucune conclusion musicale ni donnée scientifique.

Contrôles MAIN : `python -m unittest discover -s tests -p test_assistant_checkpoint_contract.py`, `php -l src/Service/AssistantJobCheckpointService.php`, `php -l src/Controller/ProfileAiRequestController.php`, puis GET HTTP sans mutation. Ce livrable ne raccorde pas encore le worker et n'achève pas la contre-expertise.
