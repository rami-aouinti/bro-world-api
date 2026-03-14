-- Remédiation des incohérences bloquant Version20260313130000.
-- Stratégie: sauvegarder puis supprimer les lignes invalides / doublons non prioritaires.
-- Compatible MySQL 8+ (ROW_NUMBER).

START TRANSACTION;

-- 0) Mesure d'impact avant remédiation.
SELECT 'invalid_target_both_null' AS anomaly, COUNT(*) AS total
FROM blog_reaction
WHERE comment_id IS NULL AND post_id IS NULL
UNION ALL
SELECT 'invalid_target_both_set', COUNT(*)
FROM blog_reaction
WHERE comment_id IS NOT NULL AND post_id IS NOT NULL
UNION ALL
SELECT 'duplicate_author_comment_rows_to_drop', COUNT(*)
FROM (
    SELECT id
    FROM (
        SELECT
            id,
            ROW_NUMBER() OVER (
                PARTITION BY author_id, comment_id
                ORDER BY COALESCE(created_at, updated_at) ASC, id ASC
            ) AS rn
        FROM blog_reaction
        WHERE comment_id IS NOT NULL
    ) ranked
    WHERE rn > 1
) t
UNION ALL
SELECT 'duplicate_author_post_rows_to_drop', COUNT(*)
FROM (
    SELECT id
    FROM (
        SELECT
            id,
            ROW_NUMBER() OVER (
                PARTITION BY author_id, post_id
                ORDER BY COALESCE(created_at, updated_at) ASC, id ASC
            ) AS rn
        FROM blog_reaction
        WHERE post_id IS NOT NULL
    ) ranked
    WHERE rn > 1
) t;

DROP TEMPORARY TABLE IF EXISTS tmp_blog_reaction_to_drop;
CREATE TEMPORARY TABLE tmp_blog_reaction_to_drop (
    id BINARY(16) PRIMARY KEY,
    reason VARCHAR(64) NOT NULL
);

-- 1) Cibles invalides.
INSERT INTO tmp_blog_reaction_to_drop (id, reason)
SELECT id, 'invalid_target_both_null'
FROM blog_reaction
WHERE comment_id IS NULL AND post_id IS NULL;

INSERT INTO tmp_blog_reaction_to_drop (id, reason)
SELECT id, 'invalid_target_both_set'
FROM blog_reaction
WHERE comment_id IS NOT NULL AND post_id IS NOT NULL
ON DUPLICATE KEY UPDATE reason = VALUES(reason);

-- 2) Doublons auteur/commentaire (on garde la ligne la plus ancienne).
INSERT INTO tmp_blog_reaction_to_drop (id, reason)
SELECT id, 'duplicate_author_comment'
FROM (
    SELECT
        id,
        ROW_NUMBER() OVER (
            PARTITION BY author_id, comment_id
            ORDER BY COALESCE(created_at, updated_at) ASC, id ASC
        ) AS rn
    FROM blog_reaction
    WHERE comment_id IS NOT NULL
) ranked
WHERE rn > 1
ON DUPLICATE KEY UPDATE reason = VALUES(reason);

-- 3) Doublons auteur/post (on garde la ligne la plus ancienne).
INSERT INTO tmp_blog_reaction_to_drop (id, reason)
SELECT id, 'duplicate_author_post'
FROM (
    SELECT
        id,
        ROW_NUMBER() OVER (
            PARTITION BY author_id, post_id
            ORDER BY COALESCE(created_at, updated_at) ASC, id ASC
        ) AS rn
    FROM blog_reaction
    WHERE post_id IS NOT NULL
) ranked
WHERE rn > 1
ON DUPLICATE KEY UPDATE reason = VALUES(reason);

-- 4) Sauvegarde des lignes supprimées.
CREATE TABLE IF NOT EXISTS blog_reaction_remediation_20260313_backup (
    id BINARY(16) NOT NULL,
    comment_id BINARY(16) DEFAULT NULL,
    post_id BINARY(16) DEFAULT NULL,
    author_id BINARY(16) NOT NULL,
    type VARCHAR(40) NOT NULL,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    remediation_reason VARCHAR(64) NOT NULL,
    remediated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO blog_reaction_remediation_20260313_backup (
    id,
    comment_id,
    post_id,
    author_id,
    type,
    created_at,
    updated_at,
    remediation_reason,
    remediated_at
)
SELECT
    br.id,
    br.comment_id,
    br.post_id,
    br.author_id,
    br.type,
    br.created_at,
    br.updated_at,
    t.reason,
    NOW()
FROM blog_reaction br
JOIN tmp_blog_reaction_to_drop t ON t.id = br.id
ON DUPLICATE KEY UPDATE
    remediation_reason = VALUES(remediation_reason),
    remediated_at = VALUES(remediated_at);

-- 5) Suppression des lignes identifiées.
DELETE br
FROM blog_reaction br
JOIN tmp_blog_reaction_to_drop t ON t.id = br.id;

-- 6) Vérification post-remédiation (doit retourner 0 partout).
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

COMMIT;
