# LAB 1.4a(30) — correction bloquante des sondes

Le contrôle du diff du commit précédent a révélé une insertion de marqueur sur la déclaration de `_prepare_imports` (CLAP), un mauvais décalage du `return {}` dans PaSST et un mauvais chemin racine dans le test. Tous les trois sont corrigés.

Aucun preset ni algorithme scientifique modifié. Les tests doivent être exécutés sous MAIN avant toute nouvelle analyse. La correction n'est pas validée par une exécution du pipeline sur MAIN.
