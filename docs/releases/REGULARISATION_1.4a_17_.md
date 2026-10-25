# Régularisation 1.4a(17) — LAB

## Contexte
Le contrat AGENTS.md impose un incrément de VERSION dans chaque livraison. Des commits précédents n'ont pas respecté ce contrat.

## Modifications
Files génériques excluant assistant.profile.review; corrections de présentation et comparaison PROFILE. Aucune modification scientifique dans ce commit.
Le présent commit aligne VERSION et archive ce constat sans réécrire l'historique partagé.

## Validation
Lecture sur GitHub de VERSION, AGENTS.md et des commits récents. La vérification des exécutions sur MAIN/XPS et les tests applicatifs restent **à effectuer** ; aucune réussite de test n'est revendiquée.

## Impact
Version déclarée 1.4a(16) → 1.4a(17). Aucune modification du code métier ou scientifique.

## Limites / risques
Les anciens commits restent non conformes individuellement : impossible de les rendre atomiques sans réécrire l'historique. Toutes les livraisons futures doivent être atomiques dès leur création.

## Reprise
Contrôler la version en runtime, les tests pertinents, et renseigner les issues de suivi avant tout nouveau changement fonctionnel.
