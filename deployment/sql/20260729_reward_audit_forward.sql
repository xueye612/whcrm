-- =====================================================================
-- 20260729_reward_audit_forward.sql
-- 幂等迁移：创建 reward_candidate_audit 审计表
-- 扩展 business_stage_reward_rule 表添加规则管理字段
-- =====================================================================

-- 1. 创建审计表（如不存在）
CREATE TABLE IF NOT EXISTS `5kcrm_reward_candidate_audit` (
  `audit_id` INT NOT NULL AUTO_INCREMENT,
  `cand_id` INT NOT NULL DEFAULT 0 COMMENT '奖惩候选ID',
  `operation_type` VARCHAR(30) NOT NULL DEFAULT 'edit' COMMENT '操作类型: edit/edit_and_reset',
  `old_data_json` TEXT COMMENT '修改前数据JSON',
  `new_data_json` TEXT COMMENT '修改后数据JSON',
  `change_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '修改原因',
  `operator_user_id` INT NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `operator_name` VARCHAR(100) DEFAULT '' COMMENT '操作人姓名',
  `operation_time` INT NOT NULL DEFAULT 0 COMMENT '操作时间戳',
  `request_ip` VARCHAR(50) DEFAULT '' COMMENT '请求IP',
  `create_time` INT NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`audit_id`),
  INDEX `idx_cand_id` (`cand_id`),
  INDEX `idx_operator` (`operator_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='奖惩候选编辑审计日志';

-- 2. 扩展 business_stage_reward_rule 表（幂等添加列）
-- rule_name 规则名称
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'rule_name');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `rule_name` VARCHAR(100) DEFAULT NULL COMMENT ''规则名称'' AFTER `rule_id`',
  'SELECT ''rule_name already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- direction 奖励或处罚
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'direction');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `direction` VARCHAR(10) DEFAULT ''reward'' COMMENT ''奖励或处罚'' AFTER `rule_name`',
  'SELECT ''direction already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- calc_method 计算方式
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'calc_method');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `calc_method` VARCHAR(10) DEFAULT ''fixed'' COMMENT ''计算方式: fixed/percent'' AFTER `amount`',
  'SELECT ''calc_method already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- single_cap 单次上限
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'single_cap');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `single_cap` DECIMAL(12,2) DEFAULT 0 COMMENT ''单次上限'' AFTER `calc_method`',
  'SELECT ''single_cap already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- monthly_cap 月度上限
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'monthly_cap');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `monthly_cap` DECIMAL(12,2) DEFAULT 0 COMMENT ''月度上限'' AFTER `single_cap`',
  'SELECT ''monthly_cap already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- need_review 是否需要审核
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'need_review');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `need_review` TINYINT DEFAULT 1 COMMENT ''是否需要审核'' AFTER `monthly_cap`',
  'SELECT ''need_review already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- auto_generate 是否允许自动生成
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'auto_generate');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `auto_generate` TINYINT DEFAULT 1 COMMENT ''是否允许自动生成'' AFTER `need_review`',
  'SELECT ''auto_generate already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- effective_date 生效日期
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'effective_date');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `effective_date` DATE DEFAULT NULL COMMENT ''生效日期'' AFTER `auto_generate`',
  'SELECT ''effective_date already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- expiry_date 失效日期
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'expiry_date');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `expiry_date` DATE DEFAULT NULL COMMENT ''失效日期'' AFTER `effective_date`',
  'SELECT ''expiry_date already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- description 规则说明
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'description');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `description` VARCHAR(500) DEFAULT NULL COMMENT ''规则说明'' AFTER `expiry_date`',
  'SELECT ''description already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT '20260729_reward_audit_forward completed' AS result;
