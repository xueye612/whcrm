-- =============================================================================
-- P4 外包项目：交付等级、需求基线/范围变更、直接成本、毛利、两类奖金池、实施三级比例分配
-- 幂等；utf8；不写入业务数据。
-- =============================================================================
CREATE TABLE IF NOT EXISTS `5kcrm_outsource_project` (
  `outsource_id` INT(11) NOT NULL AUTO_INCREMENT,
  `work_id` INT(11) NOT NULL DEFAULT 0 COMMENT '关联项目',
  `delivery_level` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '交付等级 一/二/三/四级',
  `requirement_baseline` TEXT COMMENT '需求基线',
  `scope_change` TEXT COMMENT '范围变更记录',
  `revenue` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实际到账软件及服务收入',
  `direct_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '直接成本',
  `gross_margin` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '毛利=收入-直接成本',
  `reward_pct` DECIMAL(5,2) NOT NULL DEFAULT 2.00 COMMENT '奖励池比例(%)默认2',
  `expense_pct` DECIMAL(5,2) NOT NULL DEFAULT 3.00 COMMENT '商务费用池比例(%)默认3',
  `reward_pool` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '奖励池金额',
  `expense_pool` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '商务费用池金额',
  `rules_version` VARCHAR(20) NOT NULL DEFAULT 'v1',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`outsource_id`) USING BTREE,
  INDEX `idx_ops_work`(`work_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='外包项目（P4）';

CREATE TABLE IF NOT EXISTS `5kcrm_reward_distribution` (
  `dist_id` INT(11) NOT NULL AUTO_INCREMENT,
  `source_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '外包项目/自主签单医院',
  `source_id` INT(11) NOT NULL DEFAULT 0,
  `role_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '分配角色',
  `percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '分配比例(%)',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '分配金额',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`dist_id`) USING BTREE,
  INDEX `idx_rd_source`(`source_type`,`source_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='奖励池分配（P4）';

SELECT 'P4 forward migration applied' AS result;
