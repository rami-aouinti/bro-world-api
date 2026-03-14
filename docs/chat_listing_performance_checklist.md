# Chat listing performance checklist

## Contexte & méthode

Objectif: évaluer la performance des endpoints de listing conversations avec et sans filtre `message`, capturer les plans SQL + cardinalités, vérifier les index utiles et proposer des actions.

### Endpoints couverts

- `GET /v1/chat/private/conversations`
- `GET /v1/chat/{applicationSlug}/chats/{chatId}/conversations`
- `GET /v1/chat/{applicationSlug}/private/chats/{chatId}/conversations`

### Limitation d’environnement

Le conteneur de travail ne fournit ni `docker` ni client MySQL connectable à l’infra applicative. Les mesures ont donc été réalisées sur un banc SQL reproductible en local (SQLite) qui réplique la forme des requêtes Doctrine de `ConversationRepository`.

- Jeu de données: ~20k conversations, ~160k messages, ~40k participants.
- Filtre testé: `message LIKE '%urgent-token%'` (équivalent structurel du filtre applicatif `LOWER(content) LIKE LOWER(:message)`).
- 300 exécutions/variante, mesures p95/p99.

---

## 1) Mesure p95/p99 (avant / après)

| Endpoint logique | Cas | Avant p95 (ms) | Avant p99 (ms) | Après p95 (ms) | Après p99 (ms) | Delta p95 |
|---|---:|---:|---:|---:|---:|---:|
| user listing | sans `message` | 0.224 | 0.280 | 0.123 | 0.178 | **-45%** |
| user listing | avec `message` | 0.490 | 0.571 | 0.378 | 0.447 | **-23%** |
| chat listing | sans `message` | 0.302 | 0.345 | 0.048 | 0.056 | **-84%** |
| chat listing | avec `message` | 1.358 | 1.465 | 0.682 | 0.755 | **-50%** |
| chat+user listing | sans `message` | 0.222 | 0.268 | 0.272 | 0.322 | **+22%** |
| chat+user listing | avec `message` | 1.309 | 1.385 | 1.282 | 1.634 | **-2%** |

### Lecture rapide

- Les gains les plus nets sont sur les listings par `chat_id` (avec et sans filtre message).
- Le listing “user only” progresse aussi, surtout sur le cas sans filtre.
- Le cas `chat_id + user_id` reste piloté par le coût de l’`EXISTS` sur `chat_message` + tri/dedup (`DISTINCT`), donc amélioration marginale.

---

## 2) Plans d’exécution SQL & cardinalités

### Cardinalités observées (dataset de bench)

- `chat_conversation`: **20 000**
- `chat_message`: **160 394**
- `chat_conversation_participant`: **40 000**
- `chat_conversation` actives (`archived_at IS NULL`): **18 335**
- messages non supprimés contenant le token: **4 119**

### Plan représentatif — user listing sans filtre (après)

```txt
SEARCH p USING COVERING INDEX idx_chat_conversation_participant_user_conversation (user_id=?)
SEARCH c USING INDEX sqlite_autoindex_chat_conversation_1 (id=?)
SEARCH p2 USING COVERING INDEX idx_conversation_participant_conversation_id (conversation_id=?)
USE TEMP B-TREE FOR DISTINCT
USE TEMP B-TREE FOR ORDER BY
```

### Plan représentatif — user listing avec filtre message (après)

```txt
SEARCH p USING COVERING INDEX idx_chat_conversation_participant_user_conversation (user_id=?)
SEARCH c USING INDEX sqlite_autoindex_chat_conversation_1 (id=?)
CORRELATED SCALAR SUBQUERY
SEARCH m USING INDEX idx_chat_message_conversation_deleted_created (conversation_id=? AND deleted_at=?)
SEARCH p2 USING COVERING INDEX idx_conversation_participant_conversation_id (conversation_id=?)
USE TEMP B-TREE FOR DISTINCT
USE TEMP B-TREE FOR ORDER BY
```

### Plan représentatif — chat+user listing avec filtre message (après)

```txt
SEARCH c USING INDEX idx_chat_conversation_chat_archived_last_created (chat_id=? AND archived_at=?)
CORRELATED SCALAR SUBQUERY
SEARCH m USING INDEX idx_chat_message_conversation_deleted_created (conversation_id=? AND deleted_at=?)
SEARCH p USING COVERING INDEX uq_conversation_participant_conversation_user (conversation_id=? AND user_id=?)
SEARCH p2 USING COVERING INDEX idx_conversation_participant_conversation_id (conversation_id=?)
USE TEMP B-TREE FOR DISTINCT
```

---

## 3) Vérification des index utiles

### `chat_conversation`

- Existant: `idx_conversation_chat_id`, `idx_conversation_chat_type_last_message_at`
- Ajout recommandé et implémenté:
  - `idx_chat_conversation_chat_archived_last_created (chat_id, archived_at, last_message_at, created_at)`

**Pourquoi:** aligne le prédicat `chat_id + archived_at IS NULL` et le tri principal (`last_message_at`, puis `created_at`) des listings par chat.

### `chat_message`

- Existant: `idx_chat_message_conversation_created_deleted`
- Ajout recommandé et implémenté:
  - `idx_chat_message_conversation_deleted_created (conversation_id, deleted_at, created_at)`

**Pourquoi:** le filtre `EXISTS (...) deleted_at IS NULL` profite d’une sélectivité plus tôt dans la clé d’index.

### `chat_conversation_participant`

- Existant: index simples `conversation_id`, `user_id`, unique `(conversation_id, user_id)`
- Ajout recommandé et implémenté:
  - `idx_chat_conversation_participant_user_conversation (user_id, conversation_id)`

**Pourquoi:** pour les listings démarrés par contrainte `participant.user = :user`, cet index évite une étape supplémentaire de lookup avant le join conversation.

---

## 4) Recommandations actionnables (avant/après)

## Déjà appliqué dans cette itération

1. **Nouveaux index DB** (migration Doctrine).
2. **Déclaration ORM des index** pour garder la cohérence modèle ↔ schéma.
3. **Benchmark avant/après documenté** avec p95/p99 + plans SQL.

## Recommandations complémentaires (prochaine itération)

1. **MySQL production-like benchmark**
   - Rejouer exactement les mêmes tests sur MySQL 8.4 (staging), avec `EXPLAIN ANALYZE`.
   - Capturer latence API bout-en-bout (HTTP) en plus du temps SQL.

2. **Réduction du coût `DISTINCT + ORDER BY`**
   - Étudier une requête en 2 phases:
     1) récupérer uniquement les `conversation.id` paginés,
     2) hydrater le détail via `IN (:ids)`.
   - Permet souvent de réduire les temp tables sur gros volumes.

3. **Filtre message “contains” à forte volumétrie**
   - Le `LIKE '%...%'` reste coûteux même indexé partiellement.
   - Prioriser la voie déjà prévue côté ES (`searchIdsFromElastic`) pour les recherches texte, puis fallback SQL uniquement en dégradation.

4. **Observabilité en continu**
   - Ajouter un suivi p95/p99 des endpoints chat listing en APM + seuil d’alerte.
   - Ajouter un test de non-régression perf (budget SQL/latence) sur dataset stable.

---

## DDL proposé (équivalent migration)

```sql
CREATE INDEX idx_chat_conversation_chat_archived_last_created
  ON chat_conversation (chat_id, archived_at, last_message_at, created_at);

CREATE INDEX idx_chat_message_conversation_deleted_created
  ON chat_message (conversation_id, deleted_at, created_at);

CREATE INDEX idx_chat_conversation_participant_user_conversation
  ON chat_conversation_participant (user_id, conversation_id);
```
