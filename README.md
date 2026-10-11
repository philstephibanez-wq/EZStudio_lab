# EZStudio_lab

Laboratoire de conception, d’évaluation et de validation scientifique du pipeline d’analyse musicale EZStudio.

`EZStudio_lab` sert à expérimenter, comparer, mesurer et valider des moteurs, modèles et **Presets** avant leur promotion vers EZScore. Le dépôt n’est pas le produit de lecture final : EZScore doit rester compact, stable et orienté exécution de Presets validés.

## Chaîne d’analyse

La chaîne fonctionnelle est organisée en quatre phases successives :

```text
PROFILE → STEMS → CHORDS/N → LYRICS
```

Chaque phase peut contenir plusieurs modules. Un module utilise un moteur et éventuellement un modèle, avec des réglages déclaratifs. L’ensemble de ces réglages forme des **Presets** nommés, versionnés et reproductibles.

- **PROFILE** : caractéristiques musicales globales, tempo, métrique, tonalité, accordage, descripteurs et sémantique.
- **STEMS** : séparation et sélection des sources audio utiles aux analyses suivantes.
- **CHORDS/N** : analyse harmonique, classe No-Chord `N`, grille de beats/downbeats, projection temporelle et décodage.
- **LYRICS** : transcription, alignement temporel et préparation des paroles pour le karaoké / la partition.

Le principe architectural est de séparer autant que possible :
- les moteurs et modèles de calcul ;
- les paramètres scientifiques ;
- les Presets déclaratifs ;
- l’exécution et l’orchestration.

L’objectif est de pouvoir modifier et comparer les réglages sans recoder le moteur pour chaque expérience.

## EZStudio_lab et EZScore

```text
EZStudio_lab
    ↓ expérimente / mesure / valide
Presets et modèles gagnants
    ↓ promotion contrôlée
EZScore
    ↓ applique en production
Résultat utilisateur
```

EZStudio_lab accepte les composants expérimentaux, les benchmarks et les comparaisons scientifiques nécessaires à la mise au point.

EZScore ne doit recevoir que les éléments validés nécessaires à l’exécution en production. Les mécanismes de recherche, de benchmark, d’optimisation ou d’apprentissage expérimental n’ont pas vocation à être embarqués dans EZScore.

## Architecture distribuée

Le dépôt contient le protocole partagé du calcul distribué dans :

```text
python/ezstudio/distributed/
```

Ce protocole décrit notamment :
- les capacités d’un nœud ;
- les exigences d’un job ;
- la compatibilité matériel / job ;
- la résolution contrôlée des modèles ;
- l’interdiction des substitutions silencieuses de modèles.

Le composant d’exécution distribué **EZStudioLab_assistant** possède désormais son propre dépôt Git et est intégré ici comme sous-module :

```text
EZStudioLab_assistant/
```

Initialisation après un clone :

```powershell
git clone --recurse-submodules https://github.com/philstephibanez-wq/EZStudio_lab.git
```

Pour un clone déjà existant :

```powershell
git submodule update --init --recursive
```

Le dépôt du sous-module est :

```text
https://github.com/philstephibanez-wq/EZStudioLab_assistant
```

## Modèles IA

Les modèles lourds ne doivent pas être dupliqués dans les dépôts Git.

Le protocole distribué utilise un répertoire partagé de modèles, configuré notamment par :

```text
EZSTUDIO_SHARED_MODEL_ROOT
```

Un cache local peut être utilisé lorsqu’il est explicitement configuré :

```text
EZSTUDIOLAB_ASSISTANT_MODEL_CACHE
```

La résolution d’un modèle doit être exacte et vérifiable. Aucun fallback silencieux vers un autre modèle n’est autorisé.

## Structure principale

```text
analysis/                     analyses et composants scientifiques durables
contracts/                    contrats durables
presets/                      Presets déclaratifs
python/ezstudio/              runtime et bibliothèques Python
python/ezstudio/distributed/  protocole distribué partagé
schemas/                      schémas durables
scripts/                      scripts permanents nécessaires au produit
src/                          application PHP / services
templates/                    interface
tests/                        tests durables et contrats
docs/                         documentation
docs/archive/                 documentation historique
EZStudioLab_assistant/        sous-module Git
```

La documentation historique a été déplacée sous `docs/archive/`. Le dépôt conserve **un seul `README.md` principal à sa racine**.

## Règles scientifiques

Une modification d’architecture, d’interface ou d’orchestration ne doit pas modifier silencieusement les résultats scientifiques.

En particulier :
- les baselines validées doivent être protégées contre les régressions ;
- les changements scientifiques doivent être mesurables et reproductibles ;
- un résultat externe ne constitue pas automatiquement une vérité terrain ;
- les comparaisons doivent distinguer mesures internes, références externes et validation humaine ;
- un Preset n’est promu vers EZScore qu’après validation explicite.

Pour CHORDS/N, les éléments de No-Chord, beat/downbeat, projection accord→beat et décodage temporel font partie du comportement scientifique à protéger.

## Hygiène du dépôt

Le dépôt ne doit contenir que des composants **durables et nécessaires**.

Tous les éléments temporaires doivent être créés sous :

```text
H:\temp
```

Cela inclut notamment :
- ZIP de livraison ;
- scripts `apply`, `hotfix`, `preflight`, `rollback` ponctuels ;
- sauvegardes intermédiaires ;
- logs, dumps et exports de diagnostic ;
- installateurs temporaires ;
- fichiers de test jetables ;
- caches et artefacts générés.

`H:\temp` est jetable et peut être purgé sans préavis. Le produit ne doit jamais dépendre d’un fichier qui s’y trouve.

Les règles détaillées sont définies dans :
- `AGENTS.md`
- `REPOSITORY_HYGIENE.md`

Avant tout commit :

```powershell
git status --short
git diff --check
```

Éviter `git add .` et `git add -A` lorsqu’un dépôt contient des fichiers non vérifiés.

## Développement

Le dépôt principal utilise la branche :

```text
main
```

Les changements doivent rester ciblés, testables et séparés des expériences temporaires.

Pour vérifier les sous-modules :

```powershell
git submodule status
```

Pour vérifier l’état général :

```powershell
git status
```

## Documentation historique

Les anciens documents de livraison, notes de versions et diagnostics conservés pour référence se trouvent sous :

```text
docs/archive/
```

Ils ne constituent pas le point d’entrée documentaire du projet. Ce `README.md` est la référence principale à la racine du dépôt.
