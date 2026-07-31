-- CRM arch forward migration V3 (ASCII only, idempotent, MySQL 5.7)
-- PREREQUISITE: Migration runner must verify waste tables have 0 rows before executing.
-- This SQL uses DROP TABLE IF EXISTS (safe for absent/empty tables).
-- For hard-block, use the companion PHP runner that checks row counts.

-- 1. Drop waste tables (runner-verified empty)
DROP TABLE IF EXISTS `5kcrm_lead_dedupe_log`;
DROP TABLE IF EXISTS `5kcrm_lead_raw`;
DROP TABLE IF EXISTS `5kcrm_lead_raw_batch`;
DROP TABLE IF EXISTS `5kcrm_opportunity_stage`;
DROP TABLE IF EXISTS `5kcrm_opportunity`;

-- 2. crm_business fields
SET @s1 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business' AND COLUMN_NAME='business_category')=0, 'ALTER TABLE `5kcrm_crm_business` ADD COLUMN `business_category` VARCHAR(30) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE stmt1 FROM @s1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;
SET @s2 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business' AND COLUMN_NAME='signing_method')=0, 'ALTER TABLE `5kcrm_crm_business` ADD COLUMN `signing_method` VARCHAR(20) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE stmt2 FROM @s2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
SET @s3 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business' AND COLUMN_NAME='dealer_customer_id')=0, 'ALTER TABLE `5kcrm_crm_business` ADD COLUMN `dealer_customer_id` INT(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt3 FROM @s3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- 3. Customer type column
SET @s4 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_customer' AND COLUMN_NAME='customer_type')=0, 'ALTER TABLE `5kcrm_crm_customer` ADD COLUMN `customer_type` VARCHAR(30) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE stmt4 FROM @s4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

-- 4. Dealer relationship table
CREATE TABLE IF NOT EXISTS `5kcrm_customer_dealer_rel` (
  `rel_id` INT(11) NOT NULL AUTO_INCREMENT,
  `hospital_customer_id` INT(11) NOT NULL DEFAULT 0,
  `dealer_customer_id` INT(11) NOT NULL DEFAULT 0,
  `start_time` INT(11) NOT NULL DEFAULT 0,
  `end_time` INT(11) NOT NULL DEFAULT 0,
  `change_reason` VARCHAR(500) NOT NULL DEFAULT '',
  `operator_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`rel_id`),
  INDEX `idx_cdr_hospital`(`hospital_customer_id`,`end_time`)
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci;

-- 5. Business type templates
INSERT IGNORE INTO `5kcrm_crm_business_type` (`name`,`structure_id`,`create_user_id`,`create_time`,`update_time`,`status`,`is_display`) VALUES
  ('dealer_dev','',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),1,1),
  ('hospital_direct','',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),1,1),
  ('hospital_agent','',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),1,1),
  ('outsource','',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),1,1);

-- 6. reward_candidate schema
SET @s5 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate' AND COLUMN_NAME='occurred_time')=0, 'ALTER TABLE `5kcrm_reward_candidate` ADD COLUMN `occurred_time` INT(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt5 FROM @s5; EXECUTE stmt5; DEALLOCATE PREPARE stmt5;
SET @s6 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate' AND COLUMN_NAME='business_id')=0, 'ALTER TABLE `5kcrm_reward_candidate` ADD COLUMN `business_id` INT(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt6 FROM @s6; EXECUTE stmt6; DEALLOCATE PREPARE stmt6;
SET @s7 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate' AND COLUMN_NAME='contract_id')=0, 'ALTER TABLE `5kcrm_reward_candidate` ADD COLUMN `contract_id` INT(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt7 FROM @s7; EXECUTE stmt7; DEALLOCATE PREPARE stmt7;
SET @s8 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate' AND COLUMN_NAME='customer_id')=0, 'ALTER TABLE `5kcrm_reward_candidate` ADD COLUMN `customer_id` INT(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt8 FROM @s8; EXECUTE stmt8; DEALLOCATE PREPARE stmt8;
SET @s9 = IF((SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate' AND COLUMN_NAME='source_ref')='NO', 'ALTER TABLE `5kcrm_reward_candidate` MODIFY COLUMN `source_ref` VARCHAR(100) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt9 FROM @s9; EXECUTE stmt9; DEALLOCATE PREPARE stmt9;
UPDATE `5kcrm_reward_candidate` SET `source_ref` = NULL WHERE `source_ref` = '';
SET @s10 = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate' AND INDEX_NAME='uk_source_ref')>0, 'ALTER TABLE `5kcrm_reward_candidate` DROP INDEX `uk_source_ref`', 'SELECT 1');
PREPARE stmt10 FROM @s10; EXECUTE stmt10; DEALLOCATE PREPARE stmt10;
SET @s11 = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate' AND INDEX_NAME='uk_source_type_ref')=0, 'ALTER TABLE `5kcrm_reward_candidate` ADD UNIQUE INDEX `uk_source_type_ref`(`source_type`,`source_ref`)', 'SELECT 1');
PREPARE stmt11 FROM @s11; EXECUTE stmt11; DEALLOCATE PREPARE stmt11;

-- 7. finance_record schema
SET @s12 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_finance_record' AND COLUMN_NAME='receivables_id')=0, 'ALTER TABLE `5kcrm_finance_record` ADD COLUMN `receivables_id` INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt12 FROM @s12; EXECUTE stmt12; DEALLOCATE PREPARE stmt12;
SET @s13 = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_finance_record' AND COLUMN_NAME='rel_type')=0, 'ALTER TABLE `5kcrm_finance_record` ADD COLUMN `rel_type` VARCHAR(20) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE stmt13 FROM @s13; EXECUTE stmt13; DEALLOCATE PREPARE stmt13;
SET @s14 = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_finance_record' AND INDEX_NAME='uk_receivables_id')>0, 'ALTER TABLE `5kcrm_finance_record` DROP INDEX `uk_receivables_id`', 'SELECT 1');
PREPARE stmt14 FROM @s14; EXECUTE stmt14; DEALLOCATE PREPARE stmt14;
SET @s15 = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_finance_record' AND INDEX_NAME='uk_recv_reltype')=0, 'ALTER TABLE `5kcrm_finance_record` ADD UNIQUE INDEX `uk_recv_reltype`(`receivables_id`,`rel_type`)', 'SELECT 1');
PREPARE stmt15 FROM @s15; EXECUTE stmt15; DEALLOCATE PREPARE stmt15;

SELECT 'CRM arch V3 applied' AS result;
