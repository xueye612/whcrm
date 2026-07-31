-- =====================================================================
-- 20260729_reward_candidate_ext_verify.sql
-- 验证：检查新增列和索引是否存在
-- =====================================================================

-- 验证 stage_name 列
SELECT
  IF(COUNT(*) > 0, 'PASS_stage_name', 'FAIL_stage_name') AS verify_1
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'stage_name';

-- 验证 rule_id 列
SELECT
  IF(COUNT(*) > 0, 'PASS_rule_id', 'FAIL_rule_id') AS verify_2
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'rule_id';

-- 验证 update_user_id 列（由 20260729_reward_update_user_forward 添加）
SELECT
  IF(COUNT(*) > 0, 'PASS_update_user_id', 'FAIL_update_user_id') AS verify_2b
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'update_user_id';

-- 验证 source_ref 索引
SELECT
  IF(COUNT(*) > 0, 'PASS_idx_source_ref', 'FAIL_idx_source_ref') AS verify_3
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND INDEX_NAME = 'idx_source_ref';

-- 验证 customer_ledger 统计索引
SELECT
  IF(COUNT(*) > 0, 'PASS_idx_status_register', 'FAIL_idx_status_register') AS verify_4
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_customer_ledger' AND INDEX_NAME = 'idx_status_register';

SELECT
  IF(COUNT(*) > 0, 'PASS_idx_customer_id_ledger', 'FAIL_idx_customer_id_ledger') AS verify_5
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_customer_ledger' AND INDEX_NAME = 'idx_customer_id_ledger';

SELECT
  IF(COUNT(*) > 0, 'PASS_idx_handler_ledger', 'FAIL_idx_handler_ledger') AS verify_6
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_customer_ledger' AND INDEX_NAME = 'idx_handler_ledger';

-- 汇总
SELECT
  SUM(CASE WHEN COLUMN_NAME = 'stage_name' THEN 1 ELSE 0 END) +
  SUM(CASE WHEN COLUMN_NAME = 'rule_id' THEN 1 ELSE 0 END) AS columns_ok
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate'
  AND COLUMN_NAME IN ('stage_name', 'rule_id');

SELECT 'verify complete' AS result;
