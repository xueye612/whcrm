-- ============================================================
-- 第七段 A/B：绩效状态字典 + RBAC 子权限 precheck（只读，ASCII）
-- 目标：
--   1) 校验 performance / performance_fact / responsibility_case 表存在
--   2) 校验 admin_rule 中已注册 performance_* 子权限规则
--   3) 不变更数据；forward 才会写入新规则
-- ============================================================

SELECT 'precheck_perf_tables' AS step;
SELECT t.TABLE_NAME, t.TABLE_ROWS FROM information_schema.TABLES t
WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME IN (
  '5kcrm_performance','5kcrm_performance_fact','5kcrm_responsibility_case','5kcrm_admin_rule','5kcrm_admin_access'
) ORDER BY t.TABLE_NAME;

SELECT 'precheck_perf_existing_rules' AS step;
SELECT id, pid, title, name, level, status FROM 5kcrm_admin_rule
WHERE name LIKE 'perf_%' OR (pid=442 AND name IN ('summarylist','summaryread','summarysave','rate','caselist','casesave'))
ORDER BY id;

SELECT 'precheck_perf_default_status' AS step;
SELECT COLUMN_DEFAULT AS default_perf_status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance' AND COLUMN_NAME='status';

SELECT 'precheck_perf_fact_default_status' AS step;
SELECT COLUMN_DEFAULT AS default_fact_status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact' AND COLUMN_NAME='status';

SELECT 'precheck_done' AS result;
