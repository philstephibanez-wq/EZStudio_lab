# PROFILE — navigation en fin d'analyse et terminologie (LAB 1.6a34)

## Constat et cause

Dans la recette LAB 1.6a33, le job local #215 / run #190 a atteint `completed` et `100%`. L'observateur JS interrompait alors son polling après avoir ajouté un lien manuel, sans naviguer vers le résultat. Le sélecteur du run était donc laissé avec les données du rendu précédent. Les templates conservaient des mentions « one-shot » et une bannière erronée « sans file ».

## Décision et impacts

Modifications limitées aux templates PROFILE et Catalogue, version et test. Aucun changement API, route, queue locale, orchestrateur, worker, modèle, preset, base de données ou moteur scientifique.

- **Avant** : `completed` => arrêt du polling et lien manuel ; **après** : `completed` + `done` + `100%` => **une unique navigation** vers `?song=<id>&run=<id>`.
- **Avant** : libellés anciens ; **après** : « Analyse PROFILE », « Progression scientifique PROFILE », bannière « file locale ».
- **Prévention** : annulation et erreur ne naviguent jamais automatiquement ; l'URL de destination ne contient plus le drapeau de lancement, afin d'éviter les boucles.
- **Risques** : redirection manquée si l'état scientifique est discordant ; lien manuel conservé. La navigation remplace le contenu uniquement à la terminaison, pas pendant le calcul.
- **Retour arrière** : sauvegarde contrôlée de `VERSION` et des deux templates, restauration possible par l'installateur.

## Recette MAIN

1. Preflight sur 1.6a33 avec contrôle Git blob des sources publiées.
2. Application sous réserve d'absence de job actif ; vérification ; sauvegarde dans `H:\temp`.
3. `php bin\console lint:container`; `php bin\console lint:twig templates`; `php vendor\bin\phpunit --no-progress`; `git diff --check` ; contrôler UTF-8 sans BOM.
4. Test navigateur : lancer un nouveau PROFILE. Observer progression sans remplacement global. À `completed/done/100%`, vérifier la navigation automatique, le run sélectionné et le résultat scientifique visible.
5. Tester séparément une annulation et une erreur ; aucun saut de page automatique vers un run réussi.
6. Contrôler les libellés de la page PROFILE et les deux badges du catalogue. Ne pas renommer les routes internes `oneshot` sans migration dédiée.
7. Validation de la suite FIFO / Assistant avant commit/push par le mainteneur.

## Exclusions et publication

Aucun fichier temporaire dans le dépôt ; aucun changement Orchestrator. Le test statique ne constitue pas une preuve navigateur. Version cible `1.6a34` ; livraison différentielle à publier seulement après recette réelle. Ne pas appliquer l'ancien kit R4, fondé sur la version 1.6a31.
