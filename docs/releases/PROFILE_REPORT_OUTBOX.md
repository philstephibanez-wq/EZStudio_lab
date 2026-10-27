# Événements de rapport PROFILE — outbox LAB

À l'enregistrement d'une révision d'expertise avec `status=completed`, LAB crée une ligne idempotente dans `profile_report_events` avec `revision_id`, `run_id`, état `pending`. La ligne est émise **après** la persistance de la révision. Un brouillon n'émet rien. Ce journal local préserve l'intention d'export si GitHub est momentanément indisponible.

**Non activé** : aucun watcher, aucun shell, aucun envoi GitHub depuis la requête PHP. La consommation de l'outbox par l'Orchestrator avec confirmation de publication et gestion des reprises est une livraison distincte ; ne pas activer `EZS_EVENT_REPORTS_ENABLED` ni supprimer les protections legacy pour l'instant. Les jobs scientifiques restent inchangés.
