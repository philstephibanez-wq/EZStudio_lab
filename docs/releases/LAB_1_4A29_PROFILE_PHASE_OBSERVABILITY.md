# LAB 1.4a(29) — chronométrage interne PROFILE, sans changement scientifique

Instrumentation non bloquante de CLAP, Meter et PaSST. Les marqueurs sont écrits dans `var/logs/observability/profile/phase-events/run-XXXXXX/phases.jsonl` (ou EZSTUDIO_OBSERVABILITY_ROOT).

Les marqueurs utilisent une horloge monotone et un horodatage UTC. Mesurer les intervalles entre `*_start` et `*_done`, avec prudence pour les exceptions, où un marqueur final peut manquer.

CLAP : dépendances, chargement modèle, décodage, inférence, post-traitement.
Meter : dépendances, HPSS, beats, caractéristiques, modèle Beat This, inférence.
PaSST : dépendances, resampling, sous-processus isolé (y compris son chargement modèle), post-traitement.

Les résultats de benchmark #143 et #159 sont **antérieurs** à cette instrumentation : leurs phases internes ne peuvent pas être reconstruites rétrospectivement.

## Contrats
- Aucun fichier `presets/` modifié ; aucune valeur scientifique, aucun seuil, aucun poids, aucun appel modèle ou chemin de données scientifique modifié.
- EZScore et Assistant non modifiés ; trace d'observabilité hors résultats PROFILE.
- `mark()` est best-effort : une erreur d'écriture des sondes est silencieuse.
- Les nouveaux marqueurs introduisent de petits accès disque à mesurer ; ils ne donnent pas les temps CUDA purs.
- Tests ajoutés : `python -m unittest discover -s tests/contracts -p test_profile_phase_probe_r1.py` ; **non exécutés sur MAIN** au moment de publication.
