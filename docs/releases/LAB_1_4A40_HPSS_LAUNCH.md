# v1.4a(40) — Activation pilote du cache HPSS dans PROFILE

Le test d'equivalence sur Hotel California (Librosa 0.11.0, 22050 Hz, float32, 8 630 272 echantillons) a valide une egalite exacte entre les signaux harmoniques de Meter et Tuning. L'optimisation v1.4a(39) est limitee au ModuleContext en memoire, sans stems persistants.

Pour les nouveaux jobs PROFILE, `analysis/worker_entrypoint.py` passe `EZSTUDIO_PROFILE_HPSS_REUSE=1` au sous-processus. Le journal porte `profile_hpss_reuse=1`, puis la sonde Tuning indique `EZSTUDIO_PROFILE_HPSS_REUSE cache_hit` ou `fallback`. Les autres phases restent sans activation.

Rollback : une surcharge `EZSTUDIO_PROFILE_HPSS_REUSE=0` dans l'environnement du lanceur conserve la logique historique grace a `setdefault`. La variable definie dans un PowerShell independant n'agit pas sur un serveur deja lance.

Validation : compilation/contrats sur MAIN requis, puis run PROFILE complet, comparaison des 12 modules et metrics avec run #165, verification des journaux cache_hit et temps HPSS. Le gain reel et l'absence de regression ne sont pas encore verifies.
