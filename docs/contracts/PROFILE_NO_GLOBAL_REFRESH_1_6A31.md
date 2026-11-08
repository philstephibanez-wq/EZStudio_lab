# PROFILE one-shot — retrait du rafraîchissement global · LAB 1.6a31

## Incident
En version 1.6a30, la progression apparaît puis disparaît malgré le suivi JSON R2. Le bloc historique `data-profile-live-refresh="1"` exécute `currentWrap.innerHTML = nextWrap.innerHTML`, remplaçant le DOM complet de `.wrap` (et détruisant le suivi JS du one-shot).

## Changement atomique
- Retirer **uniquement** le bloc Twig historique contenant `data-profile-live-refresh="1"`, `refreshProfileContent` et le remplacement HTML de `.wrap`.
- Conserver le suivi JSON ciblé via `profile_oneshot_status` toutes les 2 s, son historique d'étapes, la modale d'annulation et les résultats persistés.
- Ajouter un test de non-régression qui interdit explicitement le remplacement global et assure la présence du suivi JSON.
- Version LAB `1.6a30` → `1.6a31`.

## Non-impacts
Aucun changement Python, moteurs scientifiques, presets, états SQLite, historiques, orchestrator, commandes d'annulation ou d'exécution. Aucun changement de base ni lancement de job par l'installateur.

## Séquence / sécurité
Préflight de présence/uniqueness des ancres, sauvegarde externe sous `H:\temp`, application, verify, recette PHP/Twig/PHPUnit puis contrôle navigateur. Ne pas appliquer pendant une analyse active (l'application modifie le template chargé pour les requêtes suivantes). Aucun `git add/commit/push` automatisé.

## Restauration
Commande `python INSTALL.py restore <chemin BACKUP=...>`, uniquement après décision explicite. Elle restaure deux fichiers sauvegardés et enlève les deux fichiers ajoutés. Les bases ne sont jamais restaurées ni purgées.

## Preuve restant requise
La validation fonctionnelle d'un run PROFILE en cours, incluant progression persistante et annulation, doit être effectuée sur MAIN ; les tests statiques n'établissent pas à eux seuls ce comportement.
