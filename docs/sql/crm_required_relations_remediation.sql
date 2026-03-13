-- Prévisualisation et remédiation contrôlée avant migration de contraintes NOT NULL CRM.
-- Cette stratégie applique une suppression contrôlée des enregistrements orphelins.

START TRANSACTION;

-- 1) Mesurer les impacts avant suppression.
SELECT 'crm_company_without_crm' AS anomaly, COUNT(*) AS total FROM crm_company WHERE crm_id IS NULL
UNION ALL
SELECT 'crm_project_without_company', COUNT(*) FROM crm_project WHERE company_id IS NULL
UNION ALL
SELECT 'crm_sprint_without_project', COUNT(*) FROM crm_sprint WHERE project_id IS NULL
UNION ALL
SELECT 'crm_task_without_project', COUNT(*) FROM crm_task WHERE project_id IS NULL
UNION ALL
SELECT 'crm_task_request_without_task', COUNT(*) FROM crm_task_request WHERE task_id IS NULL;

-- 2) Suppression contrôlée du plus bas niveau vers le plus haut niveau.
DELETE FROM crm_task_request WHERE task_id IS NULL;
DELETE FROM crm_task WHERE project_id IS NULL;
DELETE FROM crm_sprint WHERE project_id IS NULL;
DELETE FROM crm_project WHERE company_id IS NULL;
DELETE FROM crm_company WHERE crm_id IS NULL;

-- 3) Vérification post-remédiation.
SELECT 'crm_company_without_crm' AS anomaly, COUNT(*) AS total FROM crm_company WHERE crm_id IS NULL
UNION ALL
SELECT 'crm_project_without_company', COUNT(*) FROM crm_project WHERE company_id IS NULL
UNION ALL
SELECT 'crm_sprint_without_project', COUNT(*) FROM crm_sprint WHERE project_id IS NULL
UNION ALL
SELECT 'crm_task_without_project', COUNT(*) FROM crm_task WHERE project_id IS NULL
UNION ALL
SELECT 'crm_task_request_without_task', COUNT(*) FROM crm_task_request WHERE task_id IS NULL;

COMMIT;
