## Contexte

- Ticket / contexte métier:
- Scope du changement:

## Checklist DoD Chat (obligatoire)

- [ ] Tests modifiés/ajoutés (unitaires, fonctionnels ou intégration selon le scope)
- [ ] Documentation OpenAPI alignée avec le comportement livré
- [ ] Validation accès/sécurité (authN/authZ, validation input, protections)

## API contract impact (obligatoire)

> Décrire explicitement l'impact sur le contrat API.

- Type: [ ] Aucun [ ] Non-breaking [ ] Breaking
- Endpoints / payloads concernés:
- Plan de compatibilité (si breaking):

## Perf impact (obligatoire)

> Décrire l'impact perf (DB, CPU, latence, cache), ou confirmer "Aucun impact notable" avec justification.

- Impact observé/attendu:
- Mesure(s) / preuve(s):
- Action(s) de mitigation (si nécessaire):

## Validation

- [ ] Vérifications locales exécutées
- [ ] Résultats et logs utiles ajoutés dans la PR

## Déploiement

- [ ] Aucun prérequis
- [ ] Migration de données
- [ ] Feature flag
- [ ] Rollback documenté
