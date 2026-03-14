# Chat Definition of Done (DoD)

Ce document définit les critères de validation minimum pour toute PR qui modifie le domaine **Chat**.

## Critères obligatoires

- [ ] **Tests modifiés/ajoutés** : les tests impactés existent et couvrent le changement.
- [ ] **Doc OpenAPI alignée** : les endpoints, schémas, exemples et codes d'erreur sont à jour.
- [ ] **Validation accès/sécurité** : droits d'accès, authentification, autorisations et protections d'entrée sont vérifiés.
- [ ] **API contract impact** : impact de contrat explicitement documenté (breaking/non-breaking, migration client).
- [ ] **Perf impact** : impact performance explicité (requêtes, latence, charge, cache) ou justification de non-impact.

## Règle d'application

- Les prochaines PR Chat doivent utiliser le template PR Chat.
- Une PR Chat n'est pas prête pour review tant que toutes les cases DoD obligatoires ne sont pas traitées.
