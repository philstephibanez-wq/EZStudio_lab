# v1.4a(39) — HPSS PROFILE inter-modules (pilote desactive)

Observation run #165 : Tuning ~53,15 s, dont ~29,33 s pour `librosa.effects.harmonic`. Meter realise deja `librosa.effects.hpss(y)`.

Implantation : Meter peut deposer son tableau harmonique dans le `ModuleContext` du run. Tuning ne le reprend que lorsque `EZSTUDIO_PROFILE_HPSS_REUSE=1`, `hpss_margin == 1.0`, l'entree est en float32, la forme et la frequence d'echantillonnage concordent. Sinon appel historique sans variation. Aucun fichier audio, stem, cache disque ou donnee partagee entre runs.

Important : ce mode pilote est DESACTIVE par defaut et non force par le launcher. L'equivalence theorique des valeurs ne constitue pas une preuve sur MAIN ; mesurer d'abord un extrait comparatif avec Librosa version MAIN, puis faire deux runs complets avec sortie identique. Ne pas basculer en production sans cette validation.

Contrats statiques Python a executer sur MAIN. Pour essais explicites, la variable doit etre presente dans l'environnement du lanceur du nouveau job ; configurer une console interactive non reliee au worker ne suffit pas. Rollback : variable absente ou zero.
