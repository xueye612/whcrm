-- =====================================================================
-- 20260729_reward_update_user_precheck.sql
-- 预检查：reward_candidate 表是否已存在 update_user_id 列
-- =====================================================================

-- 检查 reward_candidate 表是否存在
SELECT
  IF(COUNT(*) > 0, 'OK_reward_candidate_exists', 'FAIL_reward_candidate_missing') AS check_1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate';

-- 检查 update_user_id 列是否已存在（存在则跳过添加）
SELECT
  IF(COUNT(*) = 0, 'OK_update_user_id_not_exists_will_add', 'OK_update_user_id_already_exists_skip') AS check_2
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'update_user_id';
