# LAB 1.6a6 — Analyse PROFILE à la demande en un seul lancement

Le lot ajoute une commande Symfony explicite `lab:profile:analyze-once` pour créer **un nouveau run** en réutilisant `WorkbenchCatalog`, `AnalysisRunRegistry::createRun`, `ProfileExecutionService::queue` et `GlobalQueueProfileBridge::runOne`. Son enveloppe, moteur déclaré (`ezstudio-profile-modules/r3b31`), audio hash, preset scientifique, `automatic_next_stage=false`, worker Python canonique, persistance scientifique et attestation HMAC restent les mêmes que le contrôleur `/profile/analyze` et que le bridge 1.6a5. Aucun moteur scientifique n'est changé.

Choix limité : la commande CLI contourne **uniquement** le besoin de faire hériter une variable native à un FastCGI LAB déjà démarré ; elle n'ajoute pas une seconde implémentation des moteurs ni une queue concurrente. Le résultat peut être consulté dans la page PROFILE habituelle. Elle ne remplace pas encore le clic web pour les utilisateurs généraux.

Avant la création d'un job, `GO_PROFILE.ps1` vérifie la cohérence des secrets LAB/Orchestrator, la compatibilité PowerShell 5.1, le HTTP 422 sur requêtes invalides authentifiées et la validité de la chanson et source. Aucun ancien job n'est sélectionné ni rejoué. Le slot GPU reste arbitré par Orchestrator.

Les fichiers `.env.local` sont sauvegardés sous `H:\temp` avant modification, et les secrets ne sont jamais affichés. **Ces backups contiennent des secrets : protéger et ne pas publier.** Aucun service n'est redémarré automatiquement ; si l'API Orchestrator est déjà lancée sans la nouvelle configuration, le préflight arrête toute exécution. La procédure de redémarrage doit respecter l'intention persistante et les identités de processus ; ne jamais redémarrer PROD ou Caddy pour contourner ce blocage.

Le run réel écrit normalement dans SQLite LAB et Orchestrator. Aucune restauration automatique de données n'est possible. Une panne après création peut conserver un lease et interdit de répéter la commande en aveugle. Une réussite CLI doit être suivie de `VERIFY.php verify <id>` pour la preuve SHA, et de la comparaison avec le rapport historique **du même enregistrement/preset/modèle**, les archives `EZStudio_Run_Reports` étant partielles.

Périmètre de livraison : LAB `1.6a5` -> `1.6a6`, nouvelle commande et test ; Orchestrator `4.3a5` inchangé. Publication Git uniquement par le mainteneur, après la recette MAIN.
