# Versioning commun EZS

Règle canonique pour LAB, Assistant et Orchestrator : suffixe numérique sans parenthèses sur les nouvelles releases (`1.5a0`, `1.5a1`, `1.5a2`), puis stabilisation explicite (`1.5a`). Chaque dépôt conserve son propre numéro de version, inscrit sans préfixe `v` dans `VERSION`, et le commit commence par `[vVERSION]`. La version Orchestrator actuelle `4.2d21` n'est pas modifiée rétroactivement. Tout changement futur doit être cohérent avec la convention commune. Les anciennes formulations contradictoires ne s'appliquent plus aux nouvelles releases.
