-- ============================================================
-- 最终收口 schema 补齐 forward（幂等，MySQL 5.7 兼容）
-- 目的：补齐季度绩效新闭环所需列与表：
--   1) performance 增加 quarterly_base / reference_amount（岗位基准×评级系数参考结果）
--   2) performance_adjust_audit 调整审计表（调整前/后/原因/操作人/时间）
--   3) ledger_quality_issue 台账质量问题表（登记/确认/忽略/修正；仅已确认入绩效）
--   4) responsibility_case 增加 evidence / reviewer_user_id / review_time / review_note
--   5) reward_candidate 增加 settle_time（真实结算时间；可选，若不存在则回退 update_time）
-- ============================================================

-- 1) performance 增列（幂等）
SET @c1 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance' AND COLUMN_NAME='quarterly_base');
SET @ddl1 := IF(@c1=0, 'ALTER TABLE `5kcrm_performance` ADD COLUMN `quarterly_base` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''岗位季度基准''', 'SELECT 1');
PREPARE s1 FROM @ddl1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @c2 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance' AND COLUMN_NAME='reference_amount');
SET @ddl2 := IF(@c2=0, 'ALTER TABLE `5kcrm_performance` ADD COLUMN `reference_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''岗位基准×评级系数参考结果（仅审核）''', 'SELECT 1');
PREPARE s2 FROM @ddl2; EXECUTE s2; DEALLOCATE PREPARE s2;

-- 2) performance_adjust_audit 调整审计表
CREATE TABLE IF NOT EXISTS `5kcrm_performance_adjust_audit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `perf_id` INT(11) NOT NULL DEFAULT 0,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `period` VARCHAR(10) NOT NULL DEFAULT '',
  `changes_json` TEXT NOT NULL,
  `reason` VARCHAR(500) NOT NULL DEFAULT '',
  `operator_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_paa_perf`(`perf_id`),
  INDEX `idx_paa_user_period`(`user_id`,`period`)
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci;

-- 3) ledger_quality_issue 台账质量问题表
CREATE TABLE IF NOT EXISTS `5kcrm_ledger_quality_issue` (
  `issue_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ledger_id` INT(11) NOT NULL DEFAULT 0,
  `issue_type` VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'invalid_relation/duplicate/time_anomaly/non_standard/missing_description/status_task_mismatch/other',
  `issue_desc` VARCHAR(500) NOT NULL DEFAULT '',
  `evidence` VARCHAR(500) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT '待确认' COMMENT '待确认/已确认/已忽略/已修正',
  `register_user_id` INT(11) NOT NULL DEFAULT 0,
  `register_time` INT(11) NOT NULL DEFAULT 0,
  `confirmer_user_id` INT(11) NOT NULL DEFAULT 0,
  `confirm_time` INT(11) NOT NULL DEFAULT 0,
  `review_note` VARCHAR(500) NOT NULL DEFAULT '',
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`issue_id`),
  INDEX `idx_lqi_ledger`(`ledger_id`),
  INDEX `idx_lqi_status`(`status`)
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci;

-- 4) responsibility_case 增列（幂等）
SET @c3 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_responsibility_case' AND COLUMN_NAME='evidence');
SET @ddl3 := IF(@c3=0, 'ALTER TABLE `5kcrm_responsibility_case` ADD COLUMN `evidence` VARCHAR(500) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE s3 FROM @ddl3; EXECUTE s3; DEALLOCATE PREPARE s3;

SET @c4 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_responsibility_case' AND COLUMN_NAME='reviewer_user_id');
SET @ddl4 := IF(@c4=0, 'ALTER TABLE `5kcrm_responsibility_case` ADD COLUMN `reviewer_user_id` INT(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s4 FROM @ddl4; EXECUTE s4; DEALLOCATE PREPARE s4;

SET @c5 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_responsibility_case' AND COLUMN_NAME='review_time');
SET @ddl5 := IF(@c5=0, 'ALTER TABLE `5kcrm_responsibility_case` ADD COLUMN `review_time` INT(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s5 FROM @ddl5; EXECUTE s5; DEALLOCATE PREPARE s5;

SET @c6 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_responsibility_case' AND COLUMN_NAME='review_note');
SET @ddl6 := IF(@c6=0, 'ALTER TABLE `5kcrm_responsibility_case` ADD COLUMN `review_note` VARCHAR(500) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE s6 FROM @ddl6; EXECUTE s6; DEALLOCATE PREPARE s6;

-- 修正 responsibility_case.status 历史：将英文 '认定中' 视为中文已存在；不修改其它
-- 这里仅确保 status 默认中文
ALTER TABLE `5kcrm_responsibility_case` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT '认定中';

-- 5) reward_candidate 增加 settle_time（可选；用于真实结算时间）
SET @c7 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate' AND COLUMN_NAME='settle_time');
SET @ddl7 := IF(@c7=0, 'ALTER TABLE `5kcrm_reward_candidate` ADD COLUMN `settle_time` INT(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s7 FROM @ddl7; EXECUTE s7; DEALLOCATE PREPARE s7;

SELECT 'final_close_schema_forward_done' AS result;
