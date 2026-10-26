# LAB v1.4a(31) — attribution des 36,54 s d'imports CLAP

Le run #160 présente un intervalle de 36,54 s entre `dependency_start` et `dependency_done` pour CLAP. Trois marqueurs séparés entourent désormais exactement les instructions préexistantes `import librosa`, `import torch` et `from transformers import ClapModel, ClapProcessor`.

Ces marqueurs sont purement observatoires : aucun import, module, modèle, réglage, algorithme, preset ou résultat scientifique n'est changé. Ils utilisent le journal externe déjà présent dans `profile/phase-events/run-XXXXXX/phases.jsonl`.

**Limites :** ne peut pas reconstruire les trois durées du run #160. Une nouvelle analyse normale sera nécessaire pour les obtenir. L'import peut être sensible à l'état du cache et des modules déjà chargés.

**Tests :** test statique d'ordre des imports ajouté (`python -m unittest discover -s tests/contracts -p test_clap_import_markers_r1.py`) ; non exécuté sur MAIN au moment du commit. Compilation Python, tests de sondes précédents et tests PROFILE à exécuter sur MAIN avant analyse.
