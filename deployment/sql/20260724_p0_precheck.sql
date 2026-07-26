-- =============================================================================
-- P0 迁移前只读预检（不写入任何数据，不锁表）
-- 运维在生产库只读连接下执行，输出用于判断字段漂移和迁移安全性。
-- =============================================================================

-- 1. 数据库版本
SELECT VERSION() AS db_version;

-- 2. customer_ledger 现有列（确认 work_id/class_id/task_id 是否已存在）
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_customer_ledger'
ORDER BY ORDINAL_POSITION;

-- 3. customer_ledger 现有索引
SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_customer_ledger';

-- 4. task 表结构（确认 status 字段类型与默认值）
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_task'
  AND COLUMN_NAME IN ('task_id','status','class_id','work_id','main_user_id');

-- 5. 待新建表是否已存在（避免重复建表；CREATE TABLE IF NOT EXISTS 已兜底）
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_task_workflow','5kcrm_task_transition_log','5kcrm_task_wrk_log','5kcrm_task_test_ext','5kcrm_task_test_history');

-- 6. 台账 task_id 现有分布（确认历史关联情况，不修改）
SELECT
  COUNT(*) AS total_ledgers,
  SUM(CASE WHEN task_id > 0 THEN 1 ELSE 0 END) AS ledgers_with_task,
  SUM(CASE WHEN task_id = 0 OR task_id IS NULL THEN 1 ELSE 0 END) AS ledgers_without_task
FROM 5kcrm_customer_ledger;

-- 7. 孤儿台账指向（task_id 非零但任务不存在），用于迁移后核验
SELECT cl.ledger_id, cl.task_id
FROM 5kcrm_customer_ledger cl
LEFT JOIN 5kcrm_task t ON t.task_id = cl.task_id
WHERE cl.task_id > 0 AND t.task_id IS NULL
LIMIT 100;

-- 8. 字符集确认
SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = DATABASE();
