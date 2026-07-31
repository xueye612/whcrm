-- =====================================================================
-- 20260729_reward_candidate_ext_precheck.sql
-- 预检查：reward_candidate 表是否已存在 stage_name / rule_id 列
-- =====================================================================

-- 检查 reward_candidate 表是否存在
SELECT
  IF(COUNT(*) > 0, 'OK_reward_candidate_exists', 'FAIL_reward_candidate_missing') AS check_1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate';

-- 检查 stage_name 列是否已存在（存在则跳过添加）
SELECT
  IF(COUNT(*) = 0, 'OK_stage_name_not_exists_will_add', 'OK_stage_name_already_exists_skip') AS check_2
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'stage_name';

-- 检查 rule_id 列是否已存在
SELECT
  IF(COUNT(*) = 0, 'OK_rule_id_not_exists_will_add', 'OK_rule_id_already_exists_skip') AS check_3
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'rule_id';

-- 检查 update_user_id 列是否已存在（由 20260729_reward_update_user_forward 添加）
SELECT
  IF(COUNT(*) = 0, 'OK_update_user_id_not_exists_need_migration', 'OK_update_user_id_already_exists') AS check_3b
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'update_user_id';

-- 检查 customer_ledger 表的 auto_task_key 列（已有迁移应包含，此处仅验证）
SELECT
  IF(COUNT(*) > 0, 'OK_auto_task_key_exists', 'WARN_auto_task_key_missing_need_p0') AS check_4
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_customer_ledger' AND COLUMN_NAME = 'auto_task_key';
