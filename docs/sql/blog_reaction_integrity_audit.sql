-- Audit de l'état de la migration Version20260313130000 (blog_reaction).
-- Compatible MySQL 8+.

-- 1) Statut de migration (doit retourner "executed = 1").
SELECT
    version,
    executed_at,
    execution_time,
    1 AS executed
FROM doctrine_migration_versions
WHERE version = 'DoctrineMigrations\\Version20260313130000';

-- 2) Présence des index uniques attendus.
SELECT
    index_name,
    non_unique,
    GROUP_CONCAT(column_name ORDER BY seq_in_index) AS indexed_columns
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'blog_reaction'
  AND index_name IN ('uniq_blog_reaction_author_comment', 'uniq_blog_reaction_author_post')
GROUP BY index_name, non_unique
ORDER BY index_name;

-- 3) Présence de la CHECK constraint attendue.
SELECT
    constraint_name,
    check_clause
FROM information_schema.check_constraints
WHERE constraint_schema = DATABASE()
  AND constraint_name = 'chk_blog_reaction_exactly_one_target';

-- 4) Contrôle qualité données avant/après migration.
SELECT 'invalid_target_both_null' AS anomaly, COUNT(*) AS total
FROM blog_reaction
WHERE comment_id IS NULL AND post_id IS NULL
UNION ALL
SELECT 'invalid_target_both_set', COUNT(*)
FROM blog_reaction
WHERE comment_id IS NOT NULL AND post_id IS NOT NULL
UNION ALL
SELECT 'duplicate_author_comment', COUNT(*)
FROM (
    SELECT author_id, comment_id
    FROM blog_reaction
    WHERE comment_id IS NOT NULL
    GROUP BY author_id, comment_id
    HAVING COUNT(*) > 1
) d
UNION ALL
SELECT 'duplicate_author_post', COUNT(*)
FROM (
    SELECT author_id, post_id
    FROM blog_reaction
    WHERE post_id IS NOT NULL
    GROUP BY author_id, post_id
    HAVING COUNT(*) > 1
) d;
