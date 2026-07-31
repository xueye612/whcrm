-- =====================================================================
-- 20260729_reward_manual_rule_precheck.sql
-- 预检查：reward_manual_rule 表是否已存在
-- =====================================================================
SELECT
  IF(COUNT(*) = 0, 'OK_table_not_exists_will_create', 'OK_table_already_exists') AS check_1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_manual_rule';

-- 检查 reward_candidate 是否已有 manual_rule_id 列
SELECT
  IF(COUNT(*) = 0, 'OK_manual_rule_id_not_exists_will_add', 'OK_manual_rule_id_already_exists') AS check_2
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'manual_rule_id';
