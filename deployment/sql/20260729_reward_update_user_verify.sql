-- =====================================================================
-- 20260729_reward_update_user_verify.sql
-- 验证：检查 update_user_id 列和索引是否存在
-- =====================================================================

-- 验证 update_user_id 列
SELECT
  IF(COUNT(*) > 0, 'PASS_update_user_id', 'FAIL_update_user_id') AS verify_1
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'update_user_id';

-- 验证 update_user_id 索引
SELECT
  IF(COUNT(*) > 0, 'PASS_idx_update_user_id', 'FAIL_idx_update_user_id') AS verify_2
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND INDEX_NAME = 'idx_update_user_id';

SELECT 'verify complete' AS result;
