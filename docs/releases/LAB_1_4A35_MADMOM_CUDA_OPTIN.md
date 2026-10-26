# LAB v1.4a(35) — Madmom CUDA opt-in

- Chemin Torch CUDA utilisant les modeles historiques chargees par le processeur NumPy.
- Frontend et postprocessing Madmom conserves ; aucun preset change.
- Activation uniquement via `EZSTUDIO_PROFILE_MADMOM_BACKEND=cuda` dans le processus worker.
- Par defaut, NumPy reste actif. Si CUDA echoue, l'analyse signale l'erreur ; aucune substitution silencieuse.
- Test R3 sur 12 s : inference neuronale 2017 3,574 s CPU / 0,443 s CUDA ; 2018 2,597 s / 0,224 s. Scores differents a l'arrondi pres, classement egal sur ce seul extrait.
- Contrats statiques ajoutes. Validation MAIN et corpus multi-titres restent obligatoires avant activation du flag.
