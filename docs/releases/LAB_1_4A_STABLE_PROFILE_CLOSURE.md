# EZStudio_lab 1.4a — stabilisation de la branche PROFILE

Date : 2026-10-26. Version stable du laboratoire : **1.4a**. Dernière révision de développement antérieure : **1.4a(43)** (commit `262fdf2e41c6e18a60b21b022f5bdccc33fe7e18`). Le SHA du présent commit reste la révision Git distincte de la version produit.

## Preuves disponibles

- Référence initiale PROFILE : environ 445 s sur *Hotel California – Eagles* (historique des mesures).
- Run #166 : 173 s ; run #167 : 152 s ; Meter 44,5022 → 30,6505 s.
- Les objets `metrics` publiés pour les runs #166 et #167 sont identiques. Comparaison vérifiée dans le dépôt privé `EZStudio_Run_Reports`.
- HPSS Librosa versus `medfilt2d` : 28,948 s → 21,369 s dans le benchmark MAIN ; signaux harmonique et percussif strictement identiques (écart maximal 0).
- PaSST : lots 2 → 1, maximum de différence par chunk 4,76837158203e-7 et performance non concluante. Conserver lots de 2 (aucun changement).
- CQT : deux threads plus lents ; conserver un thread.

## Portée et limites

Ce gel porte sur **la stabilité technique de la version 1.4a et la clôture du chantier d'accélération PROFILE**. Il ne signifie **pas** que la validation scientifique PROFILE complète est terminée : tonalité ambiguë, chœurs, corpus multi-titres, comparaison externe et validation humaine restent ouverts (#13).

Aucun changement aux algorithmes dans ce commit de stabilisation. Pas de promotion de preset, aucune modification de STEMS, CHORDS/N, LYRICS, EZScore ou de l'Assistant. Les tests de contrat de 1.4a(43) ont été exécutés sur MAIN ; ce commit documentaire/version nécessite encore un pull et une vérification de VERSION sur MAIN.

## Reprise

Le travail suivant reprend dans `EZStudioLab_assistant` sur ses issues de handoff et de validation indépendante des valeurs finales LAB. Respecter AGENTS.md et les contrats propres à l'Assistant avant toute modification.
