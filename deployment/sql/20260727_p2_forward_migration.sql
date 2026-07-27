-- =============================================================================
-- P2 原始数据/有效线索：前向迁移（原始批次、原始线索池、查重决策）
-- 幂等：可重复执行；字符集 utf8；不写入业务数据，不修改现有 crm_leads 等表。
-- =============================================================================
CREATE TABLE IF NOT EXISTS `5kcrm_lead_raw_batch` (
  `batch_id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '批次名称',
  `channel` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '来源渠道',
  `submitted_by` INT(11) NOT NULL DEFAULT 0 COMMENT '提交人ID',
  `reward_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '批次奖励候选金额',
  `status` VARCHAR(20) NOT NULL DEFAULT '待处理' COMMENT '待处理/处理中/已完成',
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`batch_id`) USING BTREE,
  INDEX `idx_lrb_submitter`(`submitted_by`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='原始线索批次（P2）';

CREATE TABLE IF NOT EXISTS `5kcrm_lead_raw` (
  `raw_id` INT(11) NOT NULL AUTO_INCREMENT,
  `batch_id` INT(11) NOT NULL DEFAULT 0 COMMENT '批次ID',
  `source` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '来源',
  `raw_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '原始联系人/客户名',
  `raw_mobile` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '原始手机号',
  `raw_company` VARCHAR(150) NOT NULL DEFAULT '' COMMENT '原始公司',
  `dedupe_key` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '查重键(归一化名称+手机)',
  `status` VARCHAR(20) NOT NULL DEFAULT '待查重' COMMENT '待查重/已查重/重复/已归并/已转客户',
  `canonical_lead_id` INT(11) NOT NULL DEFAULT 0 COMMENT '归并到的标准线索ID(crm_leads)',
  `submitted_by` INT(11) NOT NULL DEFAULT 0,
  `evidence_note` VARCHAR(500) NOT NULL DEFAULT '',
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`raw_id`) USING BTREE,
  INDEX `idx_lr_batch`(`batch_id`) USING BTREE,
  INDEX `idx_lr_dedupe`(`dedupe_key`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='原始线索池（P2）';

CREATE TABLE IF NOT EXISTS `5kcrm_lead_dedupe_log` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `raw_id` INT(11) NOT NULL DEFAULT 0,
  `decision` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '归并/独立/重复',
  `canonical_lead_id` INT(11) NOT NULL DEFAULT 0,
  `decider` INT(11) NOT NULL DEFAULT 0 COMMENT '查重人ID',
  `reason` VARCHAR(500) NOT NULL DEFAULT '',
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`log_id`) USING BTREE,
  INDEX `idx_ldl_raw`(`raw_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='线索查重决策（P2）';

SELECT 'P2 forward migration applied' AS result;
