# Queue terminal sync 1.6a15 — LAB

- Le job `dispatch_owner=orchestrator` ne doit pas être annulé ou marqué failed directement par LAB.
- Synchronisation seulement après lecture du job global via API REST avec identité exacte : producteur lab, référence analysis_job:{id}, kind et global_job_id.
- Pour un global `queued`, LAB demande l'annulation au propriétaire puis reflète le résultat `cancelled`.
- Pour un global `failed` (exemple historique : analysis_job:202, global #1), LAB reflète `failed` et met le run scientifique à `error`, en conservant la cause historique. Il ne doit **jamais** présenter l'opération comme une annulation réussie.
- Une preuve de transition (`global_queue_terminal_receipts`) n'existe que dans la transaction SQLite de réconciliation ; elle est effacée avant COMMIT. Le trigger d'autorité reste actif pour toutes les autres écritures.
- Conflit local, endpoint indisponible ou job running : refus et diagnostic explicite, aucune suppression de données, aucun arrêt de processus.
- Interface : fenêtre HTML `dialog`, confirmation jQuery, fermeture sans action, style sombre intégré au Catalogue.
- Non couvert : exécuteur running, mises en queue historiques des types non routés, synchronisation asynchrone en l'absence de clic et démonstration bout en bout sur MAIN.
