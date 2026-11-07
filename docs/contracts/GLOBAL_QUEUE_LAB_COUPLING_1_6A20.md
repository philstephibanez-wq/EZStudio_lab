# LAB 1.6a20 — liaison de l’exécuteur existant

Le consommateur Orchestrator ne fournit qu'un ID opaque. LAB choisit son interpréteur Python depuis sa propre configuration (environnement `EZSTUDIO_PROFILE_PYTHON`, puis `EZSTUDIO_PYTHON`, puis chemin Python canonique MAIN déjà utilisé) et lit le seul `EZS_QUEUE_EXECUTOR_TOKEN` autorisé depuis son `.env.local` s'il est absent du processus PHP. Aucun token ne passe par la référence de job ou le processus enfant Python. Le worker scientifique, ses paramètres et les données SQLite sont inchangés.

Le service `GlobalQueueProfileBridge::runOne` conserve les contrôles d'identité, d'attestation de processus, de lease et de résultat. Une erreur après claim n'autorise jamais une relance automatique. Les anciennes admissions ne sont pas exécutées par le patch. Prérequis : statut FIFO courant et preuve des ressources Windows avant toute activation du consommateur Messenger.
