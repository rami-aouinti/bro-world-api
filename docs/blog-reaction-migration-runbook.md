# Runbook - Migration `Version20260313130000` (`blog_reaction`)

## Objectif
Valider et exploiter la migration `migrations/Version20260313130000.php` qui ajoute:

- l'index unique `(author_id, comment_id)`;
- l'index unique `(author_id, post_id)`;
- la contrainte CHECK `chk_blog_reaction_exactly_one_target`.

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

## 5) Procédure d'exploitation post-déploiement

1. **Monitoring J+0/J+1**:
   - exécuter `docs/sql/blog_reaction_integrity_audit.sql`;
   - contrôler les erreurs SQL liées à contraintes (`duplicate key`, `check constraint`) dans les logs applicatifs.
2. **Alerte**:
   - si anomalies > 0, geler les écritures concernées, exécuter la remédiation, puis relancer migration si nécessaire.
3. **Hygiène durable**:
   - conserver le backup de remédiation au moins un cycle de release;
   - archiver la sortie d'audit par environnement (preuve de conformité).
