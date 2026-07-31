-- =====================================================================
-- 20260729_reward_candidate_ext_forward.sql
-- 幂等迁移：为 reward_candidate 表添加 stage_name 和 rule_id 列
-- 为 customer_ledger 表添加统计索引
-- 不修改已有列，不删除历史数据
-- =====================================================================

-- 1. 为 reward_candidate 添加 stage_name 列（如不存在）
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'stage_name');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_reward_candidate` ADD COLUMN `stage_name` VARCHAR(100) DEFAULT NULL COMMENT ''商机阶段名称'' AFTER `reason`',
  'SELECT ''stage_name already exists, skipping'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. 为 reward_candidate 添加 rule_id 列（如不存在）
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'rule_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_reward_candidate` ADD COLUMN `rule_id` INT DEFAULT NULL COMMENT ''奖励规则ID'' AFTER `stage_name`',
  'SELECT ''rule_id already exists, skipping'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. 为 reward_candidate 添加 source_ref 索引（如不存在，用于幂等查询）
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND INDEX_NAME = 'idx_source_ref');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `5kcrm_reward_candidate` ADD INDEX `idx_source_ref` (`source_ref`(100))',
  'SELECT ''idx_source_ref already exists, skipping'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. 为 customer_ledger 添加统计索引（如不存在）
-- status + register_time 复合索引，加速统计查询
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_customer_ledger' AND INDEX_NAME = 'idx_status_register');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD INDEX `idx_status_register` (`status`, `register_time`)',
  'SELECT ''idx_status_register already exists, skipping'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. customer_id 索引（如不存在，加速按客户统计）
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_customer_ledger' AND INDEX_NAME = 'idx_customer_id_ledger');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD INDEX `idx_customer_id_ledger` (`customer_id`)',
  'SELECT ''idx_customer_id_ledger already exists, skipping'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. handler_user_id 索引（如不存在，加速按负责人统计）
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_customer_ledger' AND INDEX_NAME = 'idx_handler_ledger');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD INDEX `idx_handler_ledger` (`handler_user_id`)',
  'SELECT ''idx_handler_ledger already exists, skipping'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT '20260729_reward_candidate_ext_forward completed' AS result;
