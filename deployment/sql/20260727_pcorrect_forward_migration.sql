-- pcorrect: approval/offset/payment tracking tables (ASCII-only to avoid encoding issues)
CREATE TABLE IF NOT EXISTS `5kcrm_policy_approval` (
  `approval_id` INT(11) NOT NULL AUTO_INCREMENT,
  `approval_type` VARCHAR(30) NOT NULL DEFAULT '',
  `source_ref` VARCHAR(100) NOT NULL DEFAULT '',
  `requested_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `basis_note` VARCHAR(500) NOT NULL DEFAULT '',
  `applicant_user_id` INT(11) NOT NULL DEFAULT 0,
  `approver_user_id` INT(11) NOT NULL DEFAULT 0,
  `result` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `approve_note` VARCHAR(500) NOT NULL DEFAULT '',
  `approve_time` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`approval_id`) USING BTREE,
  INDEX `idx_pa_type_ref`(`approval_type`,`source_ref`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `5kcrm_stage_offset` (
  `offset_id` INT(11) NOT NULL AUTO_INCREMENT,
  `batch_id` INT(11) NOT NULL DEFAULT 0,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `project_ref` VARCHAR(100) NOT NULL DEFAULT '',
  `final_share` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `offset_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `net_payable` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `detail_json` TEXT,
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`offset_id`) USING BTREE,
  INDEX `idx_so_batch_user`(`batch_id`,`user_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `5kcrm_payment_tracking` (
  `payment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `project_ref` VARCHAR(100) NOT NULL DEFAULT '',
  `payment_type` VARCHAR(20) NOT NULL DEFAULT '',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `received_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `received_time` INT(11) NOT NULL DEFAULT 0,
  `confirmed_by` INT(11) NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`payment_id`) USING BTREE,
  INDEX `idx_pt_project`(`project_ref`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci;

SELECT 'pcorrect migration applied' AS result;
