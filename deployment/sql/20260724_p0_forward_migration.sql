-- =============================================================================
-- P0 前向迁移：任务工作流、台账字段补齐、轻量测试扩展、审计结构
-- 版本：20260724
-- 字符集：utf8
-- 幂等：可重复执行；已存在的表/列/索引安全跳过
-- 说明：本文件仅定义结构，不写入业务数据，不修改历史金额或历史任务状态。
--       所有新增字段允许为空，历史任务不强制补录。
-- =============================================================================

SET @db_name = DATABASE();

-- -----------------------------------------------------------------------------
-- 1. customer_ledger 补齐 work_id / class_id / task_id（修复仓库 DDL 缺失的字段漂移）
--    仅当列不存在时新增；已存在则跳过，不破坏类型。
-- -----------------------------------------------------------------------------
SET @has_work = (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='5kcrm_customer_ledger' AND COLUMN_NAME='work_id');
SET @ddl_work = IF(@has_work=0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD COLUMN `work_id` INT(11) NOT NULL DEFAULT 0 COMMENT ''项目ID'' AFTER `contract_id`;',
  'SELECT ''skip: customer_ledger.work_id exists'';');
PREPARE s FROM @ddl_work; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_class = (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='5kcrm_customer_ledger' AND COLUMN_NAME='class_id');
SET @ddl_class = IF(@has_class=0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD COLUMN `class_id` INT(11) NOT NULL DEFAULT 0 COMMENT ''任务分组ID'' AFTER `work_id`;',
  'SELECT ''skip: customer_ledger.class_id exists'';');
PREPARE s FROM @ddl_class; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_task = (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='5kcrm_customer_ledger' AND COLUMN_NAME='task_id');
SET @ddl_task = IF(@has_task=0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD COLUMN `task_id` INT(11) NOT NULL DEFAULT 0 COMMENT ''自动创建主任务ID'' AFTER `class_id`;',
  'SELECT ''skip: customer_ledger.task_id exists'';');
PREPARE s FROM @ddl_task; EXECUTE s; DEALLOCATE PREPARE s;

-- task_id 索引（便于反向同步查找），不存在时新增
SET @has_idx_task = (SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='5kcrm_customer_ledger' AND INDEX_NAME='idx_ledger_task');
SET @ddl_idx_task = IF(@has_idx_task=0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD INDEX `idx_ledger_task`(`task_id`);',
  'SELECT ''skip: idx_ledger_task exists'';');
PREPARE s FROM @ddl_idx_task; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------------------------------
-- 2. 任务工作流扩展表 5kcrm_task_workflow（一对一，仅 workflow_version=2 任务使用）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `5kcrm_task_workflow` (
  `task_id` INT(11) NOT NULL COMMENT '任务ID（主键，关联 5kcrm_task）',
  `workflow_version` TINYINT(2) NOT NULL DEFAULT 2 COMMENT '工作流版本号',
  `main_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '中文主状态',
  `aux_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '辅助状态（阻塞/暂缓/取消/重复/无需处理）',
  `aux_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '辅助状态原因',
  `init_w` VARCHAR(5) NULL DEFAULT NULL COMMENT '初始工作量等级 W1-W5',
  `init_r` VARCHAR(5) NULL DEFAULT NULL COMMENT '初始风险等级 R1-R5',
  `init_k` VARCHAR(5) NULL DEFAULT NULL COMMENT '初始专业成熟度 K1-K4',
  `final_w` VARCHAR(5) NULL DEFAULT NULL COMMENT '最终工作量等级',
  `final_r` VARCHAR(5) NULL DEFAULT NULL COMMENT '最终风险等级',
  `final_k` VARCHAR(5) NULL DEFAULT NULL COMMENT '最终专业成熟度',
  `wrk_frozen` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'W/R/K 是否已冻结',
  `acceptance_criteria` TEXT NULL COMMENT '验收标准',
  `acceptance_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '验收人ID',
  `risk_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '风险说明',
  `dependency_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '外部依赖',
  `professional_confirm` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '专业确认要求和依据',
  `plan_release_version` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '预计发布版本',
  `actual_release_version` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '实际发布版本',
  `need_release` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否需要发布门禁',
  `need_customer_verify` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否需要客户验证',
  `release_skip_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '豁免发布/客户验证原因',
  `release_skip_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '豁免操作人',
  `release_skip_time` INT(11) NOT NULL DEFAULT 0 COMMENT '豁免时间',
  `version` INT(11) NOT NULL DEFAULT 1 COMMENT '乐观锁版本号',
  `create_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '创建人',
  `update_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '更新人',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`task_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='任务工作流扩展（P0）';

-- -----------------------------------------------------------------------------
-- 3. 任务状态迁移审计 5kcrm_task_transition_log
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `5kcrm_task_transition_log` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) NOT NULL DEFAULT 0 COMMENT '任务ID',
  `action` VARCHAR(40) NOT NULL DEFAULT '' COMMENT '动作名称',
  `from_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '原主状态',
  `to_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '新主状态',
  `field_changes` TEXT NULL COMMENT '关键字段前后值（JSON 文本）',
  `reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '原因',
  `user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '操作者',
  `correlation_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '幂等/链路标识',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '时间',
  PRIMARY KEY (`log_id`) USING BTREE,
  INDEX `idx_trans_task`(`task_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='任务状态迁移审计（P0）';

-- -----------------------------------------------------------------------------
-- 4. W/R/K 调整审计 5kcrm_task_wrk_log
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `5kcrm_task_wrk_log` (
  `wrk_log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) NOT NULL DEFAULT 0 COMMENT '任务ID',
  `field_name` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '字段（init_w/final_r 等）',
  `old_value` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '原值',
  `new_value` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '新值',
  `reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '调整原因',
  `user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '调整人',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '调整时间',
  PRIMARY KEY (`wrk_log_id`) USING BTREE,
  INDEX `idx_wrk_task`(`task_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='W/R/K 调整审计（P0）';

-- -----------------------------------------------------------------------------
-- 5. 轻量测试任务扩展 5kcrm_task_test_ext（测试任务仍是 5kcrm_task 普通记录）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `5kcrm_task_test_ext` (
  `ext_id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) NOT NULL DEFAULT 0 COMMENT '测试任务ID（关联 5kcrm_task）',
  `source_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '来源类型 task/project/version/release',
  `source_id` INT(11) NOT NULL DEFAULT 0 COMMENT '来源对象ID',
  `origin_task_id` INT(11) NOT NULL DEFAULT 0 COMMENT '原研发任务ID',
  `test_type` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '测试类型',
  `test_scope` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '测试范围',
  `completion_criteria` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '完成标准',
  `tester_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '测试人员',
  `reviewer_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '指定评定人',
  `deadline` INT(11) NOT NULL DEFAULT 0 COMMENT '截止时间',
  `is_required` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否必需测试',
  `submit_status` VARCHAR(20) NOT NULL DEFAULT 'not_submitted' COMMENT '提交状态',
  `review_status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '评定状态 pending/compliant/non_compliant',
  `current_round` INT(11) NOT NULL DEFAULT 0 COMMENT '当前轮次',
  `submit_result` TEXT NULL COMMENT '提交结果',
  `submit_issues` TEXT NULL COMMENT '发现问题',
  `review_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '实际评定人',
  `review_time` INT(11) NOT NULL DEFAULT 0 COMMENT '评定时间',
  `return_reason` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '退回原因',
  `return_requirements` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '退回缺失内容',
  `return_deadline` INT(11) NOT NULL DEFAULT 0 COMMENT '重新完成期限',
  `idempotency_key` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '幂等键',
  `version` INT(11) NOT NULL DEFAULT 1 COMMENT '乐观锁',
  `create_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '发起人',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`ext_id`) USING BTREE,
  UNIQUE KEY `uk_test_task` (`task_id`) USING BTREE,
  UNIQUE KEY `uk_test_idem` (`idempotency_key`) USING BTREE,
  INDEX `idx_test_origin`(`origin_task_id`) USING BTREE,
  INDEX `idx_test_source`(`source_type`,`source_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='测试任务轻量扩展（P0）';

-- task_test_ext 补丁：reviewer_user_id 列与 idempotency_key 唯一索引（兼容已建表）
SET @has_reviewer = (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='5kcrm_task_test_ext' AND COLUMN_NAME='reviewer_user_id');
SET @ddl_rev = IF(@has_reviewer=0,
  'ALTER TABLE `5kcrm_task_test_ext` ADD COLUMN `reviewer_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT ''指定评定人'' AFTER `tester_user_id`;',
  'SELECT ''skip: reviewer_user_id exists'';');
PREPARE s FROM @ddl_rev; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx_idem = (SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='5kcrm_task_test_ext' AND INDEX_NAME='uk_test_idem');
SET @ddl_idx_idem = IF(@has_idx_idem=0,
  'ALTER TABLE `5kcrm_task_test_ext` ADD UNIQUE INDEX `uk_test_idem`(`idempotency_key`);',
  'SELECT ''skip: uk_test_idem exists'';');
PREPARE s FROM @ddl_idx_idem; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------------------------------
-- 6. 测试提交/评定历史 5kcrm_task_test_history（每轮事实留痕）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `5kcrm_task_test_history` (
  `history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) NOT NULL DEFAULT 0 COMMENT '测试任务ID',
  `round` INT(11) NOT NULL DEFAULT 0 COMMENT '轮次',
  `history_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'submit/review',
  `content` TEXT NULL COMMENT '提交结果或评定意见',
  `issues` TEXT NULL COMMENT '发现问题或退回要求',
  `review_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '评定状态（review 行有效）',
  `user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '提交人或评定人',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '时间',
  PRIMARY KEY (`history_id`) USING BTREE,
  INDEX `idx_hist_task`(`task_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='测试提交评定历史（P0）';

-- -----------------------------------------------------------------------------
-- 7. 台账自动建任务幂等键 5kcrm_customer_ledger（新增列，应用层 compare-and-set 使用）
-- -----------------------------------------------------------------------------
SET @has_auto_key = (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='5kcrm_customer_ledger' AND COLUMN_NAME='auto_task_key');
SET @ddl_auto_key = IF(@has_auto_key=0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD COLUMN `auto_task_key` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''自动建任务幂等键'' AFTER `task_id`;',
  'SELECT ''skip: customer_ledger.auto_task_key exists'';');
PREPARE s FROM @ddl_auto_key; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'P0 forward migration applied' AS result;
