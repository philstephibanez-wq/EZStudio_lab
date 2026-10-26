# LAB v1.4a(34) — sondes ordonnanceur et non-regression

Premiere etape **sequentielle**. ModuleRegistry utilise un wrapper autour du meme run_module. Avec EZSTUDIO_PROFILE_SCHEDULER_PROBE=1, une ligne de mesures additionnelles est emise par module sur stdout : duree totale incluant cleanup, CPU du processus, temps module, statut et hash audio. Aucun signal audio ni score scientifique dans la sonde. Variable absente: aucune mesure additionnelle. Aucune parallelisation et aucun changement des presets.

Le comparateur `compare_scientific` compare de maniere exacte et ordonnee les structures JSON, en excluant les seuls champs `timing` (telemetrie). Toute difference scientifique, champ manquant ou changement d'ordre bloque l'equivalence. Son integration dans un benchmark multi-titres avec sauvegarde de deux runs identiquement configures reste a realiser avant activation de l'ordonnanceur.

Tests permanents dans `tests/contracts/test_profile_optimization_guard_r1.py` : comparaison, omission de dimensions, ordre, sonde active/desactivee et invariance de l'objet resultat. Tests ajoutes mais **non executes sur MAIN** a la publication. La comparaison sur corpus diversifie et les mesures de performance restent requises.
