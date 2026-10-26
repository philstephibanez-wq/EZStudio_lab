# v1.4a(38) — Propagation de la sonde Tuning dans le worker PROFILE

Le lanceur `analysis/worker_entrypoint.py` positionne `EZSTUDIO_PROFILE_TUNING_PROBE=1` uniquement pour les nouvelles analyses PROFILE, avant le lancement du processus fils. Le parametre est transmis via `env=env` et son etat est annonce dans le journal de job.

Rollback : definir `EZSTUDIO_PROFILE_TUNING_PROBE=0` dans l'environnement du lanceur avant le demarrage d'un nouveau job. Le `setdefault` respecte cette surcharge. Les autres phases ne sont pas modifiees.

La sonde ecrit ses mesures sur stderr avec le prefixe `EZSTUDIO_PROFILE_TUNING_PROBE`. Aucun changement volontaire aux calculs, presets ou donnees de sortie.

Validation locale requise sur MAIN : `python -m compileall` et `python -m unittest discover -s tests/contracts -p test_profile_tuning_probe_launch_contract.py -v`, puis lancer un nouveau run PROFILE pour mesurer les sous-etapes. Aucun gain de performance annonce a ce stade.
