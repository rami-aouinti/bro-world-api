# Definition of Done (DoD) — School Module

Cette DoD s'applique aux tickets restants du module **School** et doit être utilisée en revue de PR avant merge.

## Checklist DoD

- [ ] **Scope sécurité validé**
  - [ ] Contrôles d'accès (authN/authZ) vérifiés sur tous les endpoints impactés.
  - [ ] Aucune exposition de données hors périmètre (tenant/organisation/utilisateur).
  - [ ] Validation/sanitation des entrées (payload, query params, tri/filtre) en place.
  - [ ] Secrets/tokens non loggés et données sensibles masquées dans les logs.

- [ ] **Tests d'intégration clés en place**
  - [ ] Parcours nominal (create/read/update/delete selon le ticket) couvert.
  - [ ] Cas d'erreur métier principal couvert (conflit, ressource inexistante, état invalide).
  - [ ] Cas sécurité couvert (accès non autorisé/interdit).
  - [ ] Contrats de réponse vérifiés (status code + structure JSON attendue).

- [ ] **Format d'erreurs standardisé**
  - [ ] Les erreurs API respectent un format unique et documenté (code, message, détails/context).
  - [ ] Mapping explicite des erreurs techniques/validation vers les codes HTTP appropriés.
  - [ ] Messages exploitables pour le client, sans fuite d'implémentation interne.

- [ ] **Documentation API à jour**
  - [ ] Endpoint(s) ajouté(s)/modifié(s) documenté(s) (requête, réponse, erreurs, exemples).
  - [ ] Changements breaking signalés explicitement.
  - [ ] Exemples de payload alignés avec l'implémentation réelle.

- [ ] **Cache invalidation maîtrisée**
  - [ ] Stratégie d'invalidation définie pour chaque write path impactant des données cachées.
  - [ ] Invalidation testée sur les scénarios principaux (create/update/delete).
  - [ ] Absence de stale data validée sur lecture post-mutation.

## Critères minimaux de couverture

- [ ] **Couverture de tests sur le périmètre modifié**
  - [ ] Minimum **80% de couverture lignes** sur le code applicatif touché par le ticket.
  - [ ] Minimum **70% de couverture branches** sur le code métier touché.
  - [ ] Toute baisse de couverture globale du module School est interdite sans dérogation Tech Lead.

## Checks CI requis (bloquants)

- [ ] Lint/format (code style) OK.
- [ ] Analyse statique OK.
- [ ] Tests unitaires OK.
- [ ] Tests d'intégration (incluant School) OK.
- [ ] Build/packaging OK.
- [ ] Génération/validation de la doc API OK.

## Validation finale (sign-off obligatoire)

- [ ] **Tech Lead** : valide la conformité technique et sécurité de la DoD.
- [ ] **QA** : valide la stratégie de test, les résultats d'exécution et les non-régressions.

> Règle de merge : aucun ticket School n'est considéré *Done* sans toutes les cases cochées et les deux validations (Tech Lead + QA).
