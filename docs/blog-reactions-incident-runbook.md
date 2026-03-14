# Runbook Incident Ops - Réactions Blog (`blog_reaction`)

## Objectif
Ce runbook décrit la gestion d'un incident de données sur les réactions blog (création/lecture incohérente, doublons, cibles invalides), de la détection à la clôture.

---

## 1) Symptômes d'incident

Déclencher ce runbook si un ou plusieurs symptômes sont observés:

- erreurs API sur les endpoints de réaction (`POST /blog/posts/{id}/reactions`, `POST /blog/comments/{id}/reactions`, suppression de réaction);
- augmentation anormale des réponses HTTP `409`, `422` ou `500` autour des opérations de réaction;
- réactions visibles en double pour un même auteur/cible;
- réaction orpheline (sans `post_id` ni `comment_id`) ou réaction pointant à la fois vers un post **et** un commentaire;
- différence entre le compteur affiché côté front et le nombre réel en base.

---

## 2) Logs clés à collecter

Période minimale recommandée: `T-30 min` à `T+15 min` autour du début de l'incident.

### Logs applicatifs

Filtrer les logs avec les motifs suivants:

- `SQLSTATE[23000]` (duplicate key / integrity constraint violation);
- `chk_blog_reaction_exactly_one_target` (violation de CHECK);
- `uniq_blog_reaction_author_post`;
- `uniq_blog_reaction_author_comment`;
- `blog_reaction` + `Integrity constraint`;
- traces de contrôleurs/services de réaction (namespace Blog/Reaction).

Exemple:

```bash
rg -n "SQLSTATE\[23000\]|chk_blog_reaction_exactly_one_target|uniq_blog_reaction_author_(post|comment)|blog_reaction" var/log/
```

### Logs base de données

- erreurs d'unicité et de contrainte CHECK sur la table `blog_reaction`;
- latence ou timeouts sur requêtes `INSERT/DELETE/SELECT` liées aux réactions;
- pics de lock wait timeout / deadlock sur cette table.

---

## 3) Métriques à surveiller

Suivre ces métriques sur la même fenêtre temporelle:

1. **Taux d'erreur API réactions** (5xx/4xx par endpoint).
2. **Volume d'écriture** sur `blog_reaction` (insert/delete par minute).
3. **P95/P99 latence** des endpoints de réactions.
4. **Nb de violations contraintes SQL** (unique/check).
5. **Différence de comptage**: compteur agrégé applicatif vs `COUNT(*)` SQL.
6. **Deadlocks / lock waits** sur `blog_reaction`.

Seuil d'alerte suggéré:

- `error_rate > 2%` sur 5 min;
- ou `violations_contrainte > 0` sur 5 min;
- ou écart de comptage persistant > 1% pendant 10 min.

---

## 4) SQL de diagnostic (lecture seule)

> Exécuter d'abord en lecture seule, sans modification des données.

### 4.1 Audit global

```bash
mysql "$DATABASE_URL" < docs/sql/blog_reaction_integrity_audit.sql
```

### 4.2 Doublons auteur/cible

```sql
-- Doublons sur réactions de post
SELECT author_id, post_id, COUNT(*) AS c
FROM blog_reaction
WHERE post_id IS NOT NULL
GROUP BY author_id, post_id
HAVING COUNT(*) > 1
ORDER BY c DESC;

-- Doublons sur réactions de commentaire
SELECT author_id, comment_id, COUNT(*) AS c
FROM blog_reaction
WHERE comment_id IS NOT NULL
GROUP BY author_id, comment_id
HAVING COUNT(*) > 1
ORDER BY c DESC;
```

### 4.3 Cibles invalides

```sql
-- Réactions sans cible
SELECT COUNT(*) AS no_target
FROM blog_reaction
WHERE post_id IS NULL AND comment_id IS NULL;

-- Réactions avec double cible
SELECT COUNT(*) AS double_target
FROM blog_reaction
WHERE post_id IS NOT NULL AND comment_id IS NOT NULL;
```

### 4.4 Impact fonctionnel

```sql
-- Top contenus affectés (posts)
SELECT post_id, COUNT(*) AS reaction_count
FROM blog_reaction
WHERE post_id IS NOT NULL
GROUP BY post_id
ORDER BY reaction_count DESC
LIMIT 20;

-- Top contenus affectés (commentaires)
SELECT comment_id, COUNT(*) AS reaction_count
FROM blog_reaction
WHERE comment_id IS NOT NULL
GROUP BY comment_id
ORDER BY reaction_count DESC
LIMIT 20;
```

---

## 5) Procédure de correction data (sécurisée)

> Ordre impératif: **backup -> dry-run -> validation -> apply -> audit post-apply**.

### 5.1 Préparation et sécurité

1. Geler temporairement les écritures de réactions (feature flag, maintenance ciblée, ou blocage API).
2. Prendre un backup logique minimal de la table:

```bash
mysqldump --single-transaction --skip-lock-tables "$DATABASE_URL" blog_reaction > backup_blog_reaction_$(date +%Y%m%d_%H%M%S).sql
```

3. Ouvrir un canal incident et noter l'horodatage de début de remédiation.

### 5.2 Dry-run (obligatoire)

Option A (recommandée): vérifier le nombre de lignes qui seraient impactées.

```sql
-- Estimation des lignes supprimées par dédoublonnage
SELECT COUNT(*) AS rows_to_delete
FROM blog_reaction br
JOIN (
  SELECT MIN(id) AS keep_id, author_id, post_id
  FROM blog_reaction
  WHERE post_id IS NOT NULL
  GROUP BY author_id, post_id
  HAVING COUNT(*) > 1
) d ON d.author_id = br.author_id
   AND d.post_id = br.post_id
WHERE br.id <> d.keep_id;
```

Option B: exécuter le script de remédiation dans une transaction et **ROLLBACK**.

```sql
START TRANSACTION;
SOURCE docs/sql/blog_reaction_integrity_remediation.sql;
-- Vérifier les compteurs, tables backup, etc.
ROLLBACK;
```

### 5.3 Validation pré-apply

Valider explicitement avant apply:

- volume attendu de suppression/modification;
- absence d'impact hors périmètre;
- accord de `Tech Lead` + `DBA`/`SRE` (4 yeux).

### 5.4 Apply

```bash
mysql "$DATABASE_URL" < docs/sql/blog_reaction_integrity_remediation.sql
mysql "$DATABASE_URL" < docs/sql/blog_reaction_integrity_audit.sql
```

Si nécessaire, rejouer ensuite les migrations/contraintes:

```bash
APP_ENV=<dev|staging|prod> php bin/console doctrine:migrations:migrate --no-interaction
```

### 5.5 Vérifications post-apply

- audit SQL à `0` anomalie;
- baisse immédiate des erreurs `SQLSTATE[23000]`;
- endpoints réactions revenus au niveau nominal (latence + succès);
- contrôle manuel de quelques posts/commentaires touchés.

---

## 6) Critères de sortie d'incident

L'incident peut être clôturé uniquement si tous les critères sont remplis:

1. `docs/sql/blog_reaction_integrity_audit.sql` ne remonte plus d'anomalies.
2. Aucun nouveau log de violation de contrainte sur une fenêtre glissante de 30 min.
3. Taux d'erreur API réaction revenu au baseline produit (ou < 0.5% sur 30 min).
4. Validation fonctionnelle par échantillonnage (création/suppression de réaction ok).
5. Rapport incident complété (timeline, cause racine, actions correctives/préventives).

---

## 7) Responsables de validation

Validation de clôture en responsabilité partagée:

- **Incident Commander (on-call)**: coordination, décision de clôture;
- **Tech Lead Backend**: validation technique de la remédiation;
- **DBA / SRE**: validation intégrité DB, verrous, performance;
- **Product Owner / QA**: validation fonctionnelle et impact utilisateur.

Signature minimale requise:

- 1 validateur technique (`Tech Lead` ou `DBA/SRE`),
- 1 validateur produit (`PO` ou `QA`),
- et confirmation finale `Incident Commander`.

---

## 8) Post-mortem (J+1)

À préparer au plus tard J+1 ouvré:

- cause racine documentée;
- actions préventives (alertes, tests d'intégrité, garde-fous applicatifs);
- décision sur automatisation d'un contrôle périodique (`audit.sql` en cron/CI ops);
- mise à jour éventuelle de ce runbook.
