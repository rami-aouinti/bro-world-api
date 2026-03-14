# Runbook - Migration `Version20260313130000` (`blog_reaction`)

## Objectif
Valider et exploiter la migration `migrations/Version20260313130000.php` qui ajoute:

- l'index unique `(author_id, comment_id)`;
- l'index unique `(author_id, post_id)`;
- la contrainte CHECK `chk_blog_reaction_exactly_one_target`.

## 0) Checklist GO/NO-GO (unique)

> Cette checklist est **obligatoire** et doit être remplie dans un unique ticket de release (ou document d'exploitation) avant toute mise en production.

| Domaine | Vérification | Statut | Preuve/lien | Validateur |
| --- | --- | --- | --- | --- |
| DB | `docs/sql/blog_reaction_integrity_audit.sql` exécuté, anomalies à `0` | ⬜ |  |  |
| Tests | Tests applicatifs de non-régression exécutés et verts | ⬜ |  |  |
| Cache | Invalidation/échauffement cache validés selon stratégie env | ⬜ |  |  |
| Visibilité | Dashboards/logs consultables (erreurs SQL, taux d'échec) | ⬜ |  |  |
| Monitoring | Alertes opérationnelles actives (DB + app) pour J+0/J+1 | ⬜ |  |  |
| Runbook | Procédure de remédiation + rollback relue et accessible | ⬜ |  |  |

### Validation obligatoire avant GO

Le passage en GO est autorisé uniquement si les trois validations suivantes sont explicitement enregistrées:

- **Dev**: conformité technique de la migration, scripts et impacts applicatifs.
- **QA**: couverture des scénarios de test et non-régression.
- **Ops**: monitoring, capacité de rollback, fenêtre d'exploitation.

Sans validation **Dev + QA + Ops**, la décision doit rester **NO-GO**.

### Journal de décision GO/NO-GO

Tracer la décision dans le ticket/document unique avec le format minimal suivant:

```text
Décision: GO | NO-GO
Date de validation: YYYY-MM-DD HH:MM TZ
Validations: Dev=<nom> ; QA=<nom> ; Ops=<nom>
Commentaires: <risques / réserves éventuelles>
```

## 1) Vérifier l'état de migration en dev / staging / prod

> Pré-requis: exécuter ces commandes depuis le conteneur/app avec dépendances PHP installées.

```bash
# DEV
APP_ENV=dev php bin/console doctrine:migrations:status --show-versions | sed -n '/20260313130000/p'

# STAGING
APP_ENV=staging php bin/console doctrine:migrations:status --show-versions | sed -n '/20260313130000/p'

# PROD
APP_ENV=prod php bin/console doctrine:migrations:status --show-versions | sed -n '/20260313130000/p'
```

Alternative SQL (sur chaque environnement) :

```sql
SELECT version, executed_at, execution_time
FROM doctrine_migration_versions
WHERE version = 'DoctrineMigrations\\Version20260313130000';
```

## 2) Contrôler les index/contraintes et la qualité des données

Lancer le script d'audit:

```bash
mysql "$DATABASE_URL" < docs/sql/blog_reaction_integrity_audit.sql
```

Résultat attendu:

- la migration est présente dans `doctrine_migration_versions`;
- `uniq_blog_reaction_author_comment` et `uniq_blog_reaction_author_post` existent avec `non_unique = 0`;
- `chk_blog_reaction_exactly_one_target` est présente;
- compteurs d'anomalies à `0`.

## 3) Remédiation si incohérences détectées puis replay migration

Si l'audit retourne des doublons ou cibles invalides:

1. Exécuter la remédiation:

```bash
mysql "$DATABASE_URL" < docs/sql/blog_reaction_integrity_remediation.sql
```

2. Rejouer la migration (environnement ciblé):

```bash
APP_ENV=<dev|staging|prod> php bin/console doctrine:migrations:migrate --no-interaction
```

3. Rejouer l'audit pour confirmation:

```bash
mysql "$DATABASE_URL" < docs/sql/blog_reaction_integrity_audit.sql
```

## 4) Rollback

### Rollback applicatif (Doctrine)

```bash
APP_ENV=<dev|staging|prod> php bin/console doctrine:migrations:execute DoctrineMigrations\\Version20260313130000 --down --no-interaction
```

Effet:

- suppression de `chk_blog_reaction_exactly_one_target`;
- suppression des index `uniq_blog_reaction_author_comment` et `uniq_blog_reaction_author_post`.

### Rollback données (si remédiation exécutée)

Les lignes supprimées sont sauvegardées dans `blog_reaction_remediation_20260313_backup`.

Restauration contrôlée (à adapter avant exécution, risque de réintroduire des incohérences):

```sql
INSERT INTO blog_reaction (id, comment_id, post_id, author_id, type, created_at, updated_at)
SELECT id, comment_id, post_id, author_id, type, created_at, updated_at
FROM blog_reaction_remediation_20260313_backup
WHERE remediated_at >= '<timestamp_de_la_fenetre_incident>';
```

## 4.1) Risques connus + stratégie de rollback

### Risques connus

- **Conflits de contraintes**: échecs en écriture si des doublons historiques subsistent (`duplicate key`).
- **Conflits métier**: rejet d'insert/update si `comment_id` et `post_id` sont simultanément NULL ou non-NULL (CHECK).
- **Perte de données ciblée**: la remédiation peut supprimer des lignes incohérentes (sauvegardées dans la table backup).
- **Bruit opérationnel**: hausse temporaire d'erreurs applicatives côté endpoints de réaction pendant la fenêtre de déploiement.

### Stratégie de rollback

1. Confirmer l'incident via logs + audit SQL.
2. Exécuter le rollback Doctrine (section 4).
3. Si remédiation effectuée, évaluer la restauration depuis `blog_reaction_remediation_20260313_backup`.
4. Rejouer l'audit SQL pour confirmer le retour à un état stable.
5. Consigner la décision finale (**GO** de reprise ou **NO-GO** prolongé) avec date/heure et validateurs Dev/QA/Ops.

## 5) Procédure d'exploitation post-déploiement

1. **Monitoring J+0/J+1**:
   - exécuter `docs/sql/blog_reaction_integrity_audit.sql`;
   - contrôler les erreurs SQL liées à contraintes (`duplicate key`, `check constraint`) dans les logs applicatifs.
2. **Alerte**:
   - si anomalies > 0, geler les écritures concernées, exécuter la remédiation, puis relancer migration si nécessaire.
3. **Hygiène durable**:
   - conserver le backup de remédiation au moins un cycle de release;
   - archiver la sortie d'audit par environnement (preuve de conformité).
