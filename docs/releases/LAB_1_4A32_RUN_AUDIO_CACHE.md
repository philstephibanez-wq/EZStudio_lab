# LAB v1.4a(32) — cache audio par run

PANNs et PaSST partagent le resultat identique de librosa.resample a 32000 Hz via ModuleContext. Cache RAM seulement, aucun stockage permanent. Aucun fichier preset ou modele change. CLAP reste inchange car son decodage source a 48000 Hz n'est pas interchangeable sans validation. Les tests du cache sont ajoutes mais ne sont pas executes sur MAIN. Avant activation: tests et comparaison de sorties scientifiques sur fichier identique.
