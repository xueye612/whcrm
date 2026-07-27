-- =============================================================================
-- P1 项目实施扩展：只读预检（不写入任何数据，不锁表）
-- 运行前在目标库只读连接下执行，判断字段漂移与迁移安全性。
-- =============================================================================

-- 1. 数据库版本
SELECT VERSION() AS db_version;

-- 2. work 表现有结构（确认无实施相关字段）
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_work'
ORDER BY ORDINAL_POSITION;

-- 3. 待新建表是否已存在（避免重复建表；CREATE TABLE IF NOT EXISTS 已兜底）
SELECT TABLE_NAME, TABLE_ROWS, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_work_profile','5kcrm_work_milestone','5kcrm_work_member_contribution','5kcrm_work_knowledge_link');

-- 4. 现有项目数量与状态分布（不修改）
SELECT
  COUNT(*) AS total_works,
  SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_works,
  SUM(CASE WHEN ishidden = 1 THEN 1 ELSE 0 END) AS hidden_works
FROM 5kcrm_work;

-- 5. 字符集确认
SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = DATABASE();

-- 6. 确认 P0 扩展表已存在（P1 依赖 P0 已落库，但不强耦合）
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_task_workflow','5kcrm_task_transition_log');
