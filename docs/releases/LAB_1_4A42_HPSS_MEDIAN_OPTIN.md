# v1.4a(42) — HPSS medfilt2d expérimental

- Défaut : `EZSTUDIO_PROFILE_HPSS_MEDIAN_BACKEND=scipy`, chemin `librosa.effects.hpss(y)` inchangé.
- Opt-in **uniquement dans le processus de travail PROFILE** : `EZSTUDIO_PROFILE_HPSS_MEDIAN_BACKEND=medfilt2d`. Ne pas activer globalement.
- Variante : SciPy `signal.medfilt2d` avec `np.pad(mode="symmetric")` pour reproduire les bordures `ndimage.median_filter(mode="reflect")`. Mêmes noyaux 31, puissance 2, `split_zeros=True`, STFT/ISTFT Librosa.
- Préflight MAIN : comparer `np.array_equal` sur les deux sorties audio longues avec le chemin standard et chronométrer chaque backend **avant toute activation**. Tests de contrat statiques uniquement au moment de la publication GitHub.
- Limites : uniquement signal mono float32/float64 ; dépend de la version Librosa 0.11 vérifiée sur MAIN. Aucune garantie de gain tant que les sorties audio complètes et PROFILE n'ont pas été testées.
- Aucun changement de CHORDS/N, STEMS, LYRICS ou EZScore. Pas de persistance de stems.
