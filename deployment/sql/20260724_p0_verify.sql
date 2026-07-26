-- =============================================================================
-- P0 迁移后只读核验（不写入数据）
-- 迁移完成后执行，确认结构正确、无孤儿、可重复执行幂等。
-- =============================================================================

-- 1. customer_ledger 新列已存在
SELECT
  (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_customer_ledger' AND COLUMN_NAME='work_id') AS has_work_id,
  (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_customer_ledger' AND COLUMN_NAME='class_id') AS has_class_id,
  (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_customer_ledger' AND COLUMN_NAME='task_id') AS has_task_id,
  (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_customer_ledger' AND COLUMN_NAME='auto_task_key') AS has_auto_task_key;

-- 2. 新建扩展表存在且为空结构（迁移不写入业务数据）
SELECT TABLE_NAME, TABLE_ROWS, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_task_workflow','5kcrm_task_transition_log','5kcrm_task_wrk_log','5kcrm_task_test_ext','5kcrm_task_test_history');

-- 3. 历史任务未被批量改写（task.status 仍为 1/2/5，无新增状态码混入）
SELECT status, COUNT(*) AS cnt FROM 5kcrm_task WHERE ishidden = 0 GROUP BY status;

-- 4. 历史台账未被自动生成任务（task_id 仍为迁移前分布）
SELECT
  COUNT(*) AS total,
  SUM(CASE WHEN task_id > 0 THEN 1 ELSE 0 END) AS with_task
FROM 5kcrm_customer_ledger;

-- 5. 幂等性二次执行测试：重复运行前向迁移应全部 skip
--    （运维可对前向迁移文件再执行一次，预期输出全部 "skip: ... exists"，无 ALTER 生效）

-- 6. 孤儿台账核验（应与预检结果一致，未新增）
SELECT COUNT(*) AS orphan_ledgers
FROM 5kcrm_customer_ledger cl
LEFT JOIN 5kcrm_task t ON t.task_id = cl.task_id
WHERE cl.task_id > 0 AND t.task_id IS NULL;
