-- =============================================================================
-- P1 实施档案补字段：远程保障时长、人员变化（制度要求补齐实施时间/里程碑/现场远程保障/人员变化/责任归因/知识链接/三档结果）
-- 幂等：列存在则跳过；兼容 MySQL 5.7。
-- =============================================================================
SET @s1 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_profile' AND COLUMN_NAME='remote_support_hours')=0,
  'ALTER TABLE `5kcrm_work_profile` ADD COLUMN `remote_support_hours` DECIMAL(8,1) NOT NULL DEFAULT 0.0 COMMENT ''远程保障时长(小时)'' AFTER `stability_days`', 'SELECT 1');
PREPARE stmt1 FROM @s1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

SET @s2 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_profile' AND COLUMN_NAME='personnel_change')=0,
  'ALTER TABLE `5kcrm_work_profile` ADD COLUMN `personnel_change` VARCHAR(500) NOT NULL DEFAULT '''' COMMENT ''人员变化记录'' AFTER `remote_support_hours`', 'SELECT 1');
PREPARE stmt2 FROM @s2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

SELECT 'P1 profile extra fields applied' AS result;
