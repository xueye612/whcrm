-- =====================================================================
-- 20260729_reward_manual_rule_forward.sql
-- 创建 reward_manual_rule 表和 reward_candidate.manual_rule_id 列
-- =====================================================================

-- 1. 创建人工奖惩项目表
CREATE TABLE IF NOT EXISTS `5kcrm_reward_manual_rule` (
  `manual_rule_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '项目名称',
  `direction` ENUM('reward','penalty') NOT NULL DEFAULT 'reward' COMMENT '奖励或处罚',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '金额（始终存正数）',
  `description` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '说明',
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `create_user_id` INT NOT NULL DEFAULT 0,
  `update_user_id` INT NOT NULL DEFAULT 0,
  `create_time` INT NOT NULL DEFAULT 0,
  `update_time` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`manual_rule_id`),
  INDEX `idx_direction_enabled` (`direction`, `is_enabled`),
  INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人工奖惩项目配置';

-- 2. 为 reward_candidate 添加 manual_rule_id 列
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'manual_rule_id');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_reward_candidate` ADD COLUMN `manual_rule_id` INT NOT NULL DEFAULT 0 COMMENT ''关联人工奖惩规则ID'' AFTER `rule_id`',
  'SELECT ''manual_rule_id already exists, skipping'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. 确保 reward_candidate_audit 表存在（阶段回退审计使用）
CREATE TABLE IF NOT EXISTS `5kcrm_reward_candidate_audit` (
  `audit_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cand_id` INT NOT NULL DEFAULT 0,
  `operation_type` VARCHAR(50) NOT NULL DEFAULT '',
  `old_data_json` TEXT,
  `new_data_json` TEXT,
  `change_reason` VARCHAR(500) NOT NULL DEFAULT '',
  `operator_user_id` INT NOT NULL DEFAULT 0,
  `operator_name` VARCHAR(50) NOT NULL DEFAULT '',
  `operation_time` INT NOT NULL DEFAULT 0,
  `request_ip` VARCHAR(50) NOT NULL DEFAULT '',
  `create_time` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`audit_id`),
  INDEX `idx_cand_id` (`cand_id`),
  INDEX `idx_operation_type` (`operation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='奖励候选审计日志';

-- 4. 插入示例人工奖惩项目
INSERT INTO `5kcrm_reward_manual_rule` (`rule_name`, `direction`, `amount`, `description`, `is_enabled`, `sort_order`, `create_time`, `update_time`)
SELECT '季度优秀表现奖', 'reward', 500, '季度评选的优秀员工奖励', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_reward_manual_rule` WHERE rule_name = '季度优秀表现奖');

INSERT INTO `5kcrm_reward_manual_rule` (`rule_name`, `direction`, `amount`, `description`, `is_enabled`, `sort_order`, `create_time`, `update_time`)
SELECT '违规处罚', 'penalty', 200, '违反公司规定的处罚', 1, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_reward_manual_rule` WHERE rule_name = '违规处罚');

SELECT '20260729_reward_manual_rule_forward completed' AS result;
