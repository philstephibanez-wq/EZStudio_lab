# v1.4a(43) — HPSS médian activé uniquement pour les nouveaux workers PROFILE

- Contexte : MAIN Librosa 0.11.0, test complet sur Hotel California : 28,948 s (référence) / 21,369 s (medfilt2d), deux sorties reconstruites exactement identiques (écart 0,0).
- Activation : le lanceur PROFILE définit par défaut `EZSTUDIO_PROFILE_HPSS_MEDIAN_BACKEND=medfilt2d` dans son environnement enfant, et journalise `profile_hpss_median_backend=`.
- Rollback sans commit : configurer explicitement `EZSTUDIO_PROFILE_HPSS_MEDIAN_BACKEND=scipy` dans l'environnement **du processus qui lance les jobs** avant le démarrage d'un nouveau worker ; `setdefault` conserve la valeur explicite. Vérifier la ligne de journal.
- Aucun changement du calcul de référence, des paramètres de Meter, du cache HPSS partagé ou des autres phases.
- Validation avant livraison : AST et contrats statiques ; MAIN doit exécuter les tests puis un run PROFILE complet. Comparer résultats métier du run témoin #166, timings, hash et preset identiques.
- Risque : validation scientifique complète démontrée pour un morceau seulement ; l'égalité sur d'autres audios et le gain total PROFILE ne sont pas encore prouvés.
