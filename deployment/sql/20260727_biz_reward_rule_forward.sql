-- business_stage_reward_rule table (ASCII only)
CREATE TABLE IF NOT EXISTS `5kcrm_business_stage_reward_rule` (
  `rule_id` INT(11) NOT NULL AUTO_INCREMENT,
  `type_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'business_type id',
  `status_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'business_status id',
  `source_type` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'reward source type label',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `rules_version` VARCHAR(20) NOT NULL DEFAULT 'v1',
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`rule_id`) USING BTREE,
  UNIQUE KEY `uk_type_status`(`type_id`,`status_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci;

SELECT 'business_stage_reward_rule applied' AS result;
