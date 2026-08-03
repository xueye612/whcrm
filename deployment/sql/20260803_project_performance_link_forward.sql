-- ======================================================================
-- FORWARD：项目实施-绩效归集 链路 前向迁移。
-- 前置条件：已执行 precheck 并处理完所有历史重复数据。
-- ======================================================================

-- 1) work_milestone：增加负责人 + 负责人索引 + 精确唯一键
ALTER TABLE `5kcrm_work_milestone`
  ADD COLUMN `responsible_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '里程碑负责人（绩效归属人）';
ALTER TABLE `5kcrm_work_milestone`
  ADD INDEX `idx_responsible_user_id` (`responsible_user_id`);
ALTER TABLE `5kcrm_work_milestone`
  ADD UNIQUE KEY `uk_milestone_exact` (`work_id`, `milestone_type`, `name`, `plan_time`, `responsible_user_id`);

-- 2) work_member_contribution：增加状态 + 确认人/确认时间 + 防重复唯一键
ALTER TABLE `5kcrm_work_member_contribution`
  ADD COLUMN `status` VARCHAR(16) NOT NULL DEFAULT '草稿' COMMENT '草稿/已确认/已作废';
ALTER TABLE `5kcrm_work_member_contribution`
  ADD COLUMN `confirm_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '确认人';
ALTER TABLE `5kcrm_work_member_contribution`
  ADD COLUMN `confirm_time` INT(11) NOT NULL DEFAULT 0 COMMENT '确认时间';
ALTER TABLE `5kcrm_work_member_contribution`
  ADD INDEX `idx_contribution_status` (`work_id`, `status`);
ALTER TABLE `5kcrm_work_member_contribution`
  ADD UNIQUE KEY `uk_contribution_exact` (`work_id`, `user_id`, `contribution_role`, `start_time`, `end_time`);

-- 3) performance_fact：统一来源唯一口径 UNIQUE(source_type, source_id)
--    仅在 precheck 确认无重复后创建。确保并发归集最终保护。
ALTER TABLE `5kcrm_performance_fact`
  DROP INDEX `uk_fact_source`;
ALTER TABLE `5kcrm_performance_fact`
  ADD UNIQUE KEY `uk_fact_source` (`source_type`, `source_id`);

-- 4) performance：同一员工、季度只允许一条汇总，保护 ensureSummary 并发创建。
ALTER TABLE `5kcrm_performance`
  DROP INDEX `idx_perf_user_period`;
ALTER TABLE `5kcrm_performance`
  ADD UNIQUE KEY `uk_perf_user_period` (`user_id`, `period`);

-- 5) 项目绩效状态变更审计，保留自动驳回、源删除撤销和重新提交历史。
CREATE TABLE IF NOT EXISTS `5kcrm_project_performance_audit` (
  `audit_id` INT(11) NOT NULL AUTO_INCREMENT,
  `fact_id` INT(11) NOT NULL DEFAULT 0,
  `source_type` VARCHAR(50) NOT NULL DEFAULT '',
  `source_id` VARCHAR(100) NOT NULL DEFAULT '',
  `action` VARCHAR(30) NOT NULL DEFAULT '',
  `from_status` VARCHAR(20) NOT NULL DEFAULT '',
  `to_status` VARCHAR(20) NOT NULL DEFAULT '',
  `note` VARCHAR(500) NOT NULL DEFAULT '',
  `operator_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`audit_id`),
  INDEX `idx_project_perf_audit_fact` (`fact_id`),
  INDEX `idx_project_perf_audit_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='项目绩效状态变更审计';
