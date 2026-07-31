-- =====================================================================
-- 20260729_reward_update_user_forward.sql
-- 幂等迁移：为 reward_candidate 表添加 update_user_id 列及索引
-- 用于记录最后修改候选状态的操作人，支持 candidateList 关联展示更新人
-- =====================================================================

-- 1. 为 reward_candidate 添加 update_user_id 列（如不存在）
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'update_user_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_reward_candidate` ADD COLUMN `update_user_id` INT NOT NULL DEFAULT 0 COMMENT ''最后修改人ID'' AFTER `create_user_id`',
  'SELECT ''update_user_id already exists, skipping'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. 为 reward_candidate 添加 update_user_id 索引（如不存在）
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND INDEX_NAME = 'idx_update_user_id');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `5kcrm_reward_candidate` ADD INDEX `idx_update_user_id` (`update_user_id`)',
  'SELECT ''idx_update_user_id already exists, skipping'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT '20260729_reward_update_user_forward completed' AS result;
