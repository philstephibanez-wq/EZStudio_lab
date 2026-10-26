# LAB 1.4a(37) — Observabilite fine Tuning

Le run #164 consomme environ 52,26 s dans Tuning. Pour comprendre le cout sans changer les calculs scientifiques, un diagnostic opt-in trace les etapes suivantes sur stderr : extraction harmonique, estimation globale, estimations par extrait, syntheses, balayage CQT global, balayages CQT par extrait et consensus.

Activation uniquement dans le processus worker PROFILE : `EZSTUDIO_PROFILE_TUNING_PROBE=1`. Aucun changement de sortie scientifique, modele, preset ou calcul. Les lignes commencent par `EZSTUDIO_PROFILE_TUNING_PROBE` et sont reprises dans les logs du job par `analysis/worker_entrypoint.py`.

Validation : compilation et contrats a executer sur MAIN. Pas encore de gain annonce ; prochaine etape : mesurer la repartition, puis implementer un seul cache ou parallelisme ayant une equivalence controlee sur plusieurs morceaux.
