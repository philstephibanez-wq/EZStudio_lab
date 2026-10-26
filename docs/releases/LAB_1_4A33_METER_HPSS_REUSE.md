# LAB v1.4a(33) — HPSS unique dans Meter

Meter appelait deux fois `librosa.effects.hpss(y)` : directement pour la composante percussive et indirectement via `engine.harmonic_novelty(y, sr, hop, bf)` pour la composante harmonique. Meter conserve les deux sorties du premier appel et reconstitue la formule originale de `harmonic_novelty` sans second HPSS. La fonction originale dans `python/engine.py` reste intacte pour CHORDS/N.

Le test scientifique de contrat compare les sorties de la formule originale et celle avec harmonique réutilisée en égalité exacte. En cas d'écart le changement n'est pas validé. Aucune modification de preset, modèle, seuil ou ordre de modules. Tests non exécutés sur MAIN au moment du commit. Les gains attendus doivent être mesurés sur des runs réels et les sorties finales comparées avant/après.
