-- =============================================================================
-- P5 奖励候选与商务费用：统一候选、规则版本、证据、人工审核、结算批次、冲销；
--       商务费用独立流程（与员工奖励物理/权限分离）。系统只建议不发放。
-- 幂等；utf8；不写入业务数据。
-- =============================================================================
CREATE TABLE IF NOT EXISTS `5kcrm_reward_candidate` (
  `cand_id` INT(11) NOT NULL AUTO_INCREMENT,
  `source_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '来源：客户成功工程师/驻场服务专员/外包需求沟通/外包方案报价/项目分配等',
  `source_ref` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '来源引用(opp_id/work_id等)',
  `user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '候选人',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '建议金额',
  `reason` VARCHAR(500) NOT NULL DEFAULT '',
  `evidence_note` VARCHAR(500) NOT NULL DEFAULT '',
  `rules_version` VARCHAR(20) NOT NULL DEFAULT 'v1',
  `status` VARCHAR(20) NOT NULL DEFAULT '待审核' COMMENT '待审核/已通过/已驳回/已结算/已冲销',
  `reviewer_user_id` INT(11) NOT NULL DEFAULT 0,
  `review_time` INT(11) NOT NULL DEFAULT 0,
  `review_note` VARCHAR(500) NOT NULL DEFAULT '',
  `batch_id` INT(11) NOT NULL DEFAULT 0,
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cand_id`) USING BTREE,
  INDEX `idx_rc_user`(`user_id`) USING BTREE,
  INDEX `idx_rc_status`(`status`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='奖励候选（P5）';

CREATE TABLE IF NOT EXISTS `5kcrm_reward_batch` (
  `batch_id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL DEFAULT '',
  `period` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '结算期 如202607',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(20) NOT NULL DEFAULT '待结算' COMMENT '待结算/已结算',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`batch_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='奖励结算批次（P5）';

CREATE TABLE IF NOT EXISTS `5kcrm_reward_offset` (
  `offset_id` INT(11) NOT NULL AUTO_INCREMENT,
  `cand_id` INT(11) NOT NULL DEFAULT 0,
  `offset_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '冲销金额(正数)',
  `reason` VARCHAR(500) NOT NULL DEFAULT '',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`offset_id`) USING BTREE,
  INDEX `idx_ro_cand`(`cand_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='奖励冲销（P5）';

CREATE TABLE IF NOT EXISTS `5kcrm_business_expense` (
  `expense_id` INT(11) NOT NULL AUTO_INCREMENT,
  `source_ref` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '关联业务引用',
  `subject` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '事项',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `external_party` VARCHAR(150) NOT NULL DEFAULT '' COMMENT '外部服务主体',
  `agreement_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '协议/凭据状态',
  `compliance_confirmed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '合规确认',
  `status` VARCHAR(20) NOT NULL DEFAULT '待审批' COMMENT '待审批/已审批/已发生',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`expense_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='商务费用（P5，与奖励分离）';

SELECT 'P5 forward migration applied' AS result;
