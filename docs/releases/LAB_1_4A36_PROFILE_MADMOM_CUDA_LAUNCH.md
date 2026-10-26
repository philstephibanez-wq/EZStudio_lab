# LAB 1.4a(36) — activation Madmom CUDA dans les jobs PROFILE

- Lorsqu'un job PROFILE est lance, `analysis/worker_entrypoint.py` injecte par defaut `EZSTUDIO_PROFILE_MADMOM_BACKEND=cuda` dans l'environnement du nouveau processus `profile/runner.py`.
- Le worker parent ne modifie pas son propre environnement. Aucun redemarrage PHP ou Caddy n'est necessaire pour les prochains jobs.
- Les jobs STEMS, CHORDS et BENCHMARK restent inchanges. Les presets et poids ne changent pas.
- Retour au backend NumPy : definir `EZSTUDIO_PROFILE_MADMOM_BACKEND=numpy` dans l'environnement du lanceur de jobs avant sa creation (prioritaire sur `setdefault`), ou revert du commit. La variable de la console interactive n'agit pas sur les serveurs deja lances.
- Preuve disponible : contrat v1.4a(35) 4/4 sur MAIN et test integre sur 12 secondes Hotel California, les deux moteurs `cuda:torch`, 24 probabilites, meme gagnant que R3.
- Risque : calcul CUDA complet pas encore valide sur un corpus multi-morceaux, temps reel PROFILE total non mesure, ecarts numeriques possibles. Surveiller les statuts et la consommation VRAM et verifier le champ device dans le rapport.
- Validation de ce commit : syntaxe/test de contrat de lancement restent a executer sur MAIN.
