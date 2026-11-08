# Queue locale : isolation des propriétaires et purge contrôlée — LAB 1.6a33

## Règle de propriété

- Les anciennes entrées `dispatch_owner=orchestrator` sont archivées et **jamais** reprises, modifiées, dédupliquées contre les nouveaux jobs LAB ni comptabilisées comme exécution active locale.
- La FIFO et l'exclusion mutuelle restent communes à `profile` et `assistant.profile.review` **seulement** pour `dispatch_owner=lab`.
- `PROFILE` individuel et `PROFILE ALL` passent toujours par la même admission ; le moteur Python/les résultats/presets restent inchangés. Le travail d'Assistant est exécuté par Assistant, jamais sur LAB.
- Aucun job historique n'est automatiquement supprimé ou annulé par la mise à jour.

## Purge logique optionnelle et distincte

- `PURGE.py preview` vérifie strictement les jobs locaux Assistant #160, #161, #181 et #185, sans écriture.
- Après décision opérateur, `PURGE.py apply` peut marquer ces quatre jobs `cancelled` **sans DELETE**, uniquement si tous sont `queued`, `dispatch_owner=lab`, `kind=assistant.profile.review`, liés à un run `profile` `done` et qu'aucun job local PROFILE ou Assistant n'est actif.
- La purge conserve les runs scientifiques et l'historique de la queue ; sauvegarde SQLite cohérente via API `sqlite3.backup()` sous `H:/temp` avant écriture ; aucune suppression des demandes appartenant à Orchestrator.
- L'annulation des anciennes demandes Assistant est un choix métier : une demande encore utile doit être traitée, non purgée.
- La purge ne déclenche pas le drain. L'activation scientifique, les scénarios de crash et la communication réelle avec Assistant demandent une recette séparée.

## Mise à niveau

- Base source exactement LAB 1.6a32 après R1C, pas R4 ni une autre livraison.
- Préflight en lecture seule sur LAB, comparaison SHA256 du service R1C et absence des nouveaux fichiers.
- Le code PHP livré est linté avant installation ; sauvegarde préalable dans `H:/temp`, vérification post-application, restauration explicite avec contrôle des empreintes.
- Aucun changement d'Orchestrator, aucun commit/push de l'installateur.
