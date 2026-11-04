# LAB — contrat REST de statut minimal (1.5a5)

## Propriété et endpoint

EZStudio_lab est le seul producteur de `GET /api/v1/service-status`, servi par Symfony sur le backend local `127.0.0.1:8701`. Le collecteur `EZS_orchestrator` 4.2e utilise ce chemin fixe et le schéma `ezs.service-status.v1`.

Accès : requête GET, hôte et client loopback. Réponse JSON, `Cache-Control: no-store`. Un appel externe reçoit 403. Aucun secret, identifiant métier ni chemin utilisateur n'est publié.

## Champs et états

- `schema` : `ezs.service-status.v1` ; `application` : `ezstudio_lab` ; `instance` : `lab`.
- `version` : valeur canonique de `VERSION`, ou `null` si illisible/invalide.
- `state` : `OK` si Symfony a servi la route et lu une VERSION valide ; `KO` sinon. Ce champ répond au contrat historique du collecteur Orchestrator, **ce n'est pas la santé de tous les composants LAB**.
- `health` : `OK` ou `ERROR` au même périmètre **minimal** ; `activity` : `UNKNOWN` (pas de mesure des tâches).
- `components` : tableau des preuves individuelles : route HTTP Symfony et version contrôlées ; base, workers et pipeline scientifique à `UNKNOWN/not_probed` jusqu'à instrumentation explicite. Aucune inférence à partir du port TCP.
- `captured_utc` : horodatage UTC ISO 8601 de la requête.

HTTP 200 si `state=OK` ; HTTP 503 si VERSION absente/invalide ; HTTP 403 si accès refusé. Aucun redémarrage, aucune action sur worker, file ou modèle. La lecture ne modifie aucune donnée.

## Impacts et non-régression

Avant : Orchestrator classait LAB `NON_IMPLEMENTE` (HTTP 404 sur 8701). Après déploiement : il peut lire `SOFT_OK` si la route Symfony et VERSION fonctionnent. Les autres composants restent `UNKNOWN`. Pas de modification de Caddyfile, WinSW, flux d'analyse, moteur Python, base SQLite, queues, Messenger, GPU, Assistant ou PROD.

## Recette et rollback

Préflight SHA/version/worktree propre ; application atomique de la nouvelle route, du test PHPUnit et de VERSION ; tests PHP/Twig et suite PHPUnit complète ; `debug:router lab_service_status_v1` ; HTTP direct 8701 puis collecteur 8790 ; contrôle des autres états hard. Le retour arrière est une restauration explicite des fichiers sauvegardés hors dépôt et la suppression du nouveau contrôleur et du nouveau test. Ne pas toucher aux états applicatifs.

## Suite

Instrumentation interne des queues et workers sous un **contrat étendu** et des preuves dédiées, sans transformer artificiellement l'état minimal en disponibilité métier complète.
