# v1.4a(41) — Pilote CQT sur extraits, sequentiel par defaut

Contexte : apres HPSS, Tuning prend 23,27 secondes (run #166), dont ~9,93 secondes precedemment pour le scan CQT des extraits. Les huit extraits sont independants.

Changement : un pilote permet au maximum deux threads pour les scans CQT **par extrait** ; aucun changement du scan global, de la grille d'offsets, des seuils, des candidats ou du consensus. `ThreadPoolExecutor.map` conserve l'ordre. Par defaut, `EZSTUDIO_PROFILE_CQT_WORKERS=1` : execution sequentielle historique. `EZSTUDIO_PROFILE_CQT_WORKERS=2` : test isole a comparer a #166.

Attention : le parallelisme peut etre moins performant selon Numba, FFT et charge CPU ; aucune acceleration n'est annoncee avant mesure. Tests statiques MAIN requis, puis analyse comparative avec variable presente dans le *processus lanceur* (une console PowerShell independante n'a pas d'effet). Le mode par defaut doit conserver les sorties scientifiques ; le pilote ne doit etre conserve que si les resultats sont identiques et le temps reel meilleur.

Rollback : variable absente, invalide ou egale a 1. Aucun changement EZScore DEV/PROD, presets, stems, disque, autres modules.
