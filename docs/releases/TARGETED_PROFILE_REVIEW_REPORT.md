# LAB — publication ciblée d'une expertise PROFILE 1.4a (rapport synthétique)

Commande sans polling et **sans scan publiant des lots** : `scripts/publish_review_event.py --revision-id N --dry-run`, puis sans `--dry-run` seulement après bascule sûre. Le script lit la DB en mode read-only, vérifie scientifique `profile/done` et expertise `completed`, utilise le rapport sanitizé du publieur existant et ne met à jour que le fichier `published.json` lors d'un envoi effectif.

Ce script **ne détecte pas l'état des tâches Windows** et ne doit être exécuté qu'après vérification par l'Orchestrator que publisher/watcher récurrents sont inactifs. Ce commit ne démarre aucun job, ne touche pas aux scripts de tâches planifiées et ne déclenche pas de publication. Aucune automatisation du signal de finalisation n'est encore mise en service.
