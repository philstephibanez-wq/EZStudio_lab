# Contrat read-only de contexte job Assistant — LAB 1.4a(25)

Endpoint authentifié `GET /internal/analysis/desktop/profile/ai-requests/{job_id}/context`.

Le contrat relie explicitement `analysis_jobs.id` (job Assistant) à `scientific_runs.id` et `song_id` par jointure SQLite et vérifie `kind=assistant.profile.review`, `item=profile`, analyse terminée, statut de demande `queued|running`. Les valeurs publiques proviennent exclusivement de `ProfileExternalReviewService::assertionsForRun` (`final_assertions`). Aucun moteur, score, chemin, diagnostic, preset ni provenance scientifique dans l'enveloppe HTTP. `version` reste null si inconnue.

Ce endpoint **ne claim pas** la demande #181, ne la termine pas et ne modifie aucune donnée. Il fournit la pièce préalable à une exécution sûre ; le véritable runtime de recherche Web, vérification, retour LAB et contrôle E2E sont encore absents. Pas de relance de PROFILE. Tester syntaxe PHP, projection réelle sur #181 et non-fuite avant de permettre le claim.
