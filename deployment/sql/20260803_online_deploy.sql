-- ======================================================================
-- whcrm 项目实施-绩效归集 线上部署 SQL（幂等，可直接在线上执行）
-- 包含本轮所有变更的表结构，不含演示数据。
-- 已存在的列/索引/表会自动跳过。
-- ======================================================================

-- ========== 1. 新建表（IF NOT EXISTS） ==========

-- 1.1 项目里程碑
CREATE TABLE IF NOT EXISTS `5kcrm_work_milestone` (
  `milestone_id` int(11) NOT NULL AUTO_INCREMENT,
  `work_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `milestone_type` varchar(20) NOT NULL DEFAULT '' COMMENT '里程碑类型',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '里程碑名称',
  `responsible_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '里程碑负责人（绩效归属人）',
  `plan_time` int(11) NOT NULL DEFAULT '0' COMMENT '计划时间',
  `actual_time` int(11) NOT NULL DEFAULT '0' COMMENT '实际时间',
  `status` varchar(20) NOT NULL DEFAULT '未开始' COMMENT '状态：未开始/进行中/已完成/已延期',
  `sort` int(11) NOT NULL DEFAULT '0',
  `evidence_note` varchar(500) NOT NULL DEFAULT '' COMMENT '证据/说明',
  `create_user_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`milestone_id`),
  KEY `idx_milestone_work` (`work_id`),
  KEY `idx_responsible_user_id` (`responsible_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='项目里程碑';

-- 1.2 项目成员贡献
CREATE TABLE IF NOT EXISTS `5kcrm_work_member_contribution` (
  `contribution_id` int(11) NOT NULL AUTO_INCREMENT,
  `work_id` int(11) NOT NULL DEFAULT '0' COMMENT '项目ID',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '贡献人ID',
  `contribution_role` varchar(50) NOT NULL DEFAULT '' COMMENT '贡献角色',
  `status` varchar(16) NOT NULL DEFAULT '草稿' COMMENT '草稿/已确认/已作废',
  `on_site_days` decimal(6,1) NOT NULL DEFAULT '0.0' COMMENT '现场人日',
  `start_time` int(11) NOT NULL DEFAULT '0' COMMENT '开始时间',
  `end_time` int(11) NOT NULL DEFAULT '0' COMMENT '结束时间',
  `confirm_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '确认人',
  `confirm_time` int(11) NOT NULL DEFAULT '0' COMMENT '确认时间',
  `evidence_note` varchar(500) NOT NULL DEFAULT '' COMMENT '证据/说明',
  `create_user_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`contribution_id`),
  KEY `idx_contrib_work` (`work_id`),
  KEY `idx_contrib_user` (`user_id`),
  KEY `idx_contribution_status` (`work_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='项目成员贡献';

-- 1.3 项目实施档案
CREATE TABLE IF NOT EXISTS `5kcrm_work_profile` (
  `work_id` int(11) NOT NULL COMMENT '项目ID（主键）',
  `project_type` varchar(20) NOT NULL DEFAULT '' COMMENT '项目类型',
  `impl_level` varchar(20) NOT NULL DEFAULT '' COMMENT '实施等级',
  `stability_days` int(11) NOT NULL DEFAULT '0' COMMENT '稳定期（天）',
  `remote_support_hours` decimal(8,1) NOT NULL DEFAULT '0.0' COMMENT '远程保障时长(小时)',
  `personnel_change` varchar(500) NOT NULL DEFAULT '' COMMENT '人员变化',
  `acceptance_result` varchar(20) NOT NULL DEFAULT '' COMMENT '验收结果',
  `acceptance_user_id` int(11) NOT NULL DEFAULT '0',
  `acceptance_time` int(11) NOT NULL DEFAULT '0',
  `plan_start_time` int(11) NOT NULL DEFAULT '0',
  `plan_end_time` int(11) NOT NULL DEFAULT '0',
  `actual_start_time` int(11) NOT NULL DEFAULT '0',
  `actual_end_time` int(11) NOT NULL DEFAULT '0',
  `risk_note` varchar(500) NOT NULL DEFAULT '' COMMENT '风险与责任归因',
  `version` int(11) NOT NULL DEFAULT '1' COMMENT '乐观锁版本号',
  `create_user_id` int(11) NOT NULL DEFAULT '0',
  `update_user_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`work_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='项目实施档案';

-- 1.4 项目知识链接
CREATE TABLE IF NOT EXISTS `5kcrm_work_knowledge_link` (
  `link_id` int(11) NOT NULL AUTO_INCREMENT,
  `work_id` int(11) NOT NULL DEFAULT '0',
  `link_type` varchar(20) NOT NULL DEFAULT '',
  `title` varchar(200) NOT NULL DEFAULT '',
  `url` varchar(500) NOT NULL DEFAULT '',
  `owner_user_id` int(11) NOT NULL DEFAULT '0',
  `completeness_status` varchar(20) NOT NULL DEFAULT '待补充',
  `sort` int(11) NOT NULL DEFAULT '0',
  `create_user_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`link_id`),
  KEY `idx_knowledge_work` (`work_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='项目知识链接';

-- 1.5 绩效汇总
CREATE TABLE IF NOT EXISTS `5kcrm_performance` (
  `perf_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0',
  `period` varchar(10) NOT NULL DEFAULT '',
  `duty_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `task_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `quality_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `collab_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `weighted_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) NOT NULL DEFAULT '待确认',
  `create_user_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`perf_id`),
  UNIQUE KEY `uk_perf_user_period` (`user_id`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='绩效汇总';

-- 1.6 绩效事实
CREATE TABLE IF NOT EXISTS `5kcrm_performance_fact` (
  `fact_id` int(11) NOT NULL AUTO_INCREMENT,
  `perf_id` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `period` varchar(10) NOT NULL DEFAULT '',
  `dimension` varchar(30) NOT NULL DEFAULT '' COMMENT 'duty/task/quality/collab',
  `direction` varchar(10) NOT NULL DEFAULT '正向',
  `fact_type` varchar(50) NOT NULL DEFAULT '',
  `title` varchar(200) NOT NULL DEFAULT '',
  `source_type` varchar(50) NOT NULL DEFAULT '',
  `source_id` varchar(100) NOT NULL DEFAULT '',
  `occurred_time` int(11) NOT NULL DEFAULT '0',
  `evidence` varchar(500) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT '待审核',
  `submit_user_id` int(11) NOT NULL DEFAULT '0',
  `reviewer_user_id` int(11) NOT NULL DEFAULT '0',
  `review_time` int(11) NOT NULL DEFAULT '0',
  `review_note` varchar(500) NOT NULL DEFAULT '',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`fact_id`),
  UNIQUE KEY `uk_fact_source` (`source_type`, `source_id`, `period`),
  KEY `idx_fact_perf` (`perf_id`),
  KEY `idx_fact_user_period` (`user_id`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='绩效事实';

-- 1.7 外包项目档案
CREATE TABLE IF NOT EXISTS `5kcrm_outsource_project` (
  `outsource_id` int(11) NOT NULL AUTO_INCREMENT,
  `work_id` int(11) NOT NULL DEFAULT '0',
  `delivery_level` varchar(20) NOT NULL DEFAULT '',
  `revenue` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '到账收入',
  `direct_cost` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '直接成本',
  `gross_margin` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '毛利',
  `reward_pct` decimal(5,2) NOT NULL DEFAULT '2.00' COMMENT '奖励池比例%',
  `expense_pct` decimal(5,2) NOT NULL DEFAULT '3.00' COMMENT '费用池比例%',
  `reward_pool` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '奖励池',
  `expense_pool` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '费用池',
  `requirement_baseline` text,
  `create_user_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`outsource_id`),
  UNIQUE KEY `uk_outsource_work` (`work_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='外包项目档案';

-- 1.8 奖金分配记录
CREATE TABLE IF NOT EXISTS `5kcrm_reward_distribution` (
  `dist_id` int(11) NOT NULL AUTO_INCREMENT,
  `source_type` varchar(30) NOT NULL DEFAULT '' COMMENT '来源类型',
  `source_id` int(11) NOT NULL DEFAULT '0' COMMENT '来源ID',
  `role_name` varchar(50) NOT NULL DEFAULT '' COMMENT '岗位角色',
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '分配比例%',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '分配金额',
  `create_user_id` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`dist_id`),
  KEY `idx_dist_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='奖金分配记录';

-- 1.9 绩效调整审计
CREATE TABLE IF NOT EXISTS `5kcrm_performance_adjust_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perf_id` int(11) NOT NULL DEFAULT '0',
  `operator_user_id` int(11) NOT NULL DEFAULT '0',
  `action` varchar(20) NOT NULL DEFAULT '',
  `field_name` varchar(50) NOT NULL DEFAULT '',
  `old_value` varchar(200) NOT NULL DEFAULT '',
  `new_value` varchar(200) NOT NULL DEFAULT '',
  `note` varchar(500) NOT NULL DEFAULT '',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_audit_perf` (`perf_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='绩效调整审计';

-- 1.10 责任认定
CREATE TABLE IF NOT EXISTS `5kcrm_responsibility_case` (
  `case_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0',
  `period` varchar(10) NOT NULL DEFAULT '',
  `case_type` varchar(30) NOT NULL DEFAULT '',
  `severity` varchar(20) NOT NULL DEFAULT '一般',
  `direction` varchar(10) NOT NULL DEFAULT '负向',
  `title` varchar(200) NOT NULL DEFAULT '',
  `evidence` varchar(500) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT '待审核',
  `submit_user_id` int(11) NOT NULL DEFAULT '0',
  `reviewer_user_id` int(11) NOT NULL DEFAULT '0',
  `review_time` int(11) NOT NULL DEFAULT '0',
  `review_note` varchar(500) NOT NULL DEFAULT '',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`case_id`),
  KEY `idx_case_user_period` (`user_id`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='责任认定';

-- ========== 2. 补列（针对已存在的表，幂等） ==========

-- 2.1 work_milestone.responsible_user_id
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_milestone' AND COLUMN_NAME='responsible_user_id') = 0,
  'ALTER TABLE `5kcrm_work_milestone` ADD COLUMN `responsible_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT ''里程碑负责人''', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.2 work_milestone 索引
SET @s = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_milestone' AND INDEX_NAME='idx_responsible_user_id') = 0,
  'ALTER TABLE `5kcrm_work_milestone` ADD INDEX `idx_responsible_user_id` (`responsible_user_id`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.3 work_member_contribution.status
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_member_contribution' AND COLUMN_NAME='status') = 0,
  'ALTER TABLE `5kcrm_work_member_contribution` ADD COLUMN `status` VARCHAR(16) NOT NULL DEFAULT ''草稿'' COMMENT ''草稿/已确认/已作废''', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.4 work_member_contribution.confirm_user_id
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_member_contribution' AND COLUMN_NAME='confirm_user_id') = 0,
  'ALTER TABLE `5kcrm_work_member_contribution` ADD COLUMN `confirm_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT ''确认人''', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.5 work_member_contribution.confirm_time
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_member_contribution' AND COLUMN_NAME='confirm_time') = 0,
  'ALTER TABLE `5kcrm_work_member_contribution` ADD COLUMN `confirm_time` INT(11) NOT NULL DEFAULT 0 COMMENT ''确认时间''', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.6 work_member_contribution 状态索引
SET @s = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_member_contribution' AND INDEX_NAME='idx_contribution_status') = 0,
  'ALTER TABLE `5kcrm_work_member_contribution` ADD INDEX `idx_contribution_status` (`work_id`, `status`)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========== 3. 验证 ==========
SELECT '=== 验证结果 ===' AS info;
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
  '5kcrm_work_milestone','5kcrm_work_member_contribution','5kcrm_work_profile','5kcrm_work_knowledge_link',
  '5kcrm_performance','5kcrm_performance_fact','5kcrm_outsource_project','5kcrm_reward_distribution',
  '5kcrm_performance_adjust_audit','5kcrm_responsibility_case'
) ORDER BY TABLE_NAME;
