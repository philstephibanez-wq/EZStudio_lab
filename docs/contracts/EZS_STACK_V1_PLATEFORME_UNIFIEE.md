# EZS Stack V1 — Plateforme unifiée, LAB (mise à jour 1.4c)

## Décision du mainteneur
Périmètre : Orchestrator, LAB, Assistant sur MAIN uniquement. EZScore reporté. Une seule **distribution de dépendances PHP communes** : mêmes paquets Symfony/PHPUnit et mêmes versions effectivement verrouillées dans les trois applications, indépendamment de leur nom et de leur autoload. Les dossiers `vendor/` restent privés à chaque dépôt ; l'autoload généré, propre au projet, n'a pas à être identique octet pour octet. `composer.lock` de chaque dépôt doit permettre de vérifier l'identité de la liste des packages installés, avec versions ET références. Aucun `vendor/` partagé et aucune mise à niveau implicite.

## Technologies
- PHP 8.5 sur MAIN ; Symfony 7.4 LTS, Twig, Console, Process, Messenger, Monolog, PHPUnit 13, BrowserKit/CssSelector et WebProfiler 7.4.
- Frontend : Bootstrap 5 et jQuery 3.7, versions exactes et approvisionnement local à normaliser séparément (ni Composer ni CDN implicite).
- Python et modèles IA conservés ; pytest pour tests Python concernés. Aucune réécriture scientifique en PHP.
- Rôles : Orchestrator réserve/ordonnance ; LAB garde tous ses jobs scientifiques, moteurs, presets et résultats ; Assistant contre-expertise exclusivement les assertions musicales finales de LAB.

## Règles bloquantes de livraison
- `composer.json` LAB adopte le jeu technique de référence et les extensions réellement utilisées (pdo/sqlite). Les autres dépôts devront converger vers **la même union validée** ; les dépendances applicatives facultatives doivent être justifiées ou supprimées par lot indépendant.
- `composer.lock` LAB **n'est pas livré dans ce lot** car l'environnement de préparation ne possède pas le résolveur Composer complet. Le mainteneur doit produire le verrou sur MAIN, contrôler son diff et comparer les paquets avec le référentiel avant commit. Tant que les trois locks ne sont pas équivalents, afficher `ALIGNEMENT_DEPENDANCES_EN_COURS`, jamais `UNIFIE_OK`.
- PHPUnit ciblé et complet obligatoire ; nouveaux tests vérifient les routes d'exécution LAB et les contrats des services sans lancer de job. Ces tests **n'établissent ni parité métier ni fonctionnement effectif**.
- Maintenir la queue LAB tant que le remplacement central n'a pas été prouvé, ne jamais activer deux claims sur une même ressource. Messenger est installé sans routage activé. Trois tentatives métier maximum ; toute ressource GPU reste occupée si arrêt non attesté.
- Préflight strict HEAD/version, diff, ZIP/SHA, UTF-8 sans BOM, sauvegarde hors dépôt, Composer validate/lock, PHP lint, lint:container, tests PHPUnit, vérification des messages et de l'absence de changement des services/PROD. Commit et push par mainteneur.

## Impacts
| Domaine | Modification | Prévention | Preuve |
|---|---|---|---|
| Dépendances PHP | Ajout des paquets techniques standard, rétrogradation WebProfiler 8.1 -> 7.4 | Résolution ciblée et contrôle strict du lock, pas de démarrage auto | composer validate, Composer install, lint:container |
| Tests | PHPUnit 13 et 3 tests de contrat | Sans base ni worker réel, échec en cas de dérive API | composer test ; suite existante/pytest inchangés |
| Science / IA | Aucune modification | Éviter toute transformation de traitement | Diff du lot ; non-régression ultérieure |
| Queue / Messenger | Aucune activation | Aucun consumer ni routage | Contrôle configuration et boot |
| Exploitation / PROD | Aucun changement de processus | Tests et restauration séparés | Recette MAIN, statut Git |

## Suite
1. Installer le delta LAB, générer et vérifier `composer.lock`, exécuter PHPUnit et baseline complète ; publier seulement après réussite.
2. Préparer le delta Assistant à partir de sa version GitHub courante avec exactement les mêmes contraintes et contrôles.
3. Comparer les trois verrous par liste de paquets/version/référence, ajuster Orchestrator si nécessaire dans un lot séparé.
4. Ensuite seulement : integration Messenger, toujours sans modifier les moteurs scientifiques.

## Règle unique de versioning EZS

- Ouverture de release : `1.5a0` (LAB) ; `1.7a0` (Assistant au lot suivant).
- Correctifs : `1.5a1`, `1.5a2`, etc. ; ne pas ajouter de parenthèses.
- Stabilisation : `1.5a`, sur décision explicite après recette.
- La valeur exacte du fichier `VERSION` est reprise dans le préfixe du commit `[vVERSION]`.
- Même convention dans les trois dépôts, tout en respectant les versions déjà publiées d'Orchestrator.
- Les anciennes conventions contradictoires sont remplacées pour les prochaines versions.

## Vérification d'identité des vendors

La cible est un même ensemble de packages PHP et de versions verrouillées pour les trois services, sans partager physiquement `vendor/`.
Après mise à jour ciblée du `composer.lock` de LAB, vérifier la liste normalisée `(name,version,reference)` de `packages` et `packages-dev` contre le verrou Orchestrator.
La présence d'une dépendance spécifique doit être justifiée et inscrite au contrat ; aucune identité des vendors n'est revendiquée avant comparaison effective.
Le `composer.lock` doit être généré et conservé dans le même lot que `composer.json` après contrôle MAIN.
Bootstrap 5 et jQuery 3.7 sont alignés dans les assets frontend indépendamment de Composer.
