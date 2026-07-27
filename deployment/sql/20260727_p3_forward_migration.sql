-- =============================================================================
-- P3 经销商/医院/外包机会：中文阶段、阶段证据、固定阶段奖励与重复防护
-- 幂等；字符集 utf8；不写入业务数据，不改现有表。
-- =============================================================================
CREATE TABLE IF NOT EXISTS `5kcrm_opportunity` (
  `opp_id` INT(11) NOT NULL AUTO_INCREMENT,
  `source_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '经销商/医院/外包',
  `name` VARCHAR(150) NOT NULL DEFAULT '' COMMENT '机会名称',
  `customer_id` INT(11) NOT NULL DEFAULT 0 COMMENT '关联客户(可空)',
  `current_stage` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '当前中文阶段',
  `owner_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '当前负责人',
  `status` VARCHAR(20) NOT NULL DEFAULT '进行中' COMMENT '进行中/已成交/已终止',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`opp_id`) USING BTREE,
  INDEX `idx_opp_type`(`source_type`) USING BTREE,
  INDEX `idx_opp_owner`(`owner_user_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='行业机会（P3）';

CREATE TABLE IF NOT EXISTS `5kcrm_opportunity_stage` (
  `stage_id` INT(11) NOT NULL AUTO_INCREMENT,
  `opp_id` INT(11) NOT NULL DEFAULT 0,
  `stage` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '中文阶段',
  `evidence_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '阶段证据',
  `reward_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '本阶段固定奖励金额',
  `reward_claimed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=已计入奖励候选',
  `operator` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stage_id`) USING BTREE,
  UNIQUE KEY `uk_opp_stage`(`opp_id`,`stage`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='机会阶段与奖励（P3）';

SELECT 'P3 forward migration applied' AS result;
