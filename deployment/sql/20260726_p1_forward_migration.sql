-- =============================================================================
-- P1 前向迁移：项目实施扩展（实施档案、四类里程碑、成员贡献、知识链接）
-- 版本：20260726
-- 字符集：utf8
-- 幂等：可重复执行；已存在的表安全跳过
-- 说明：仅定义结构，不写入业务数据，不修改历史项目状态。所有新增字段允许为空。
-- =============================================================================

SET @db_name = DATABASE();

-- -----------------------------------------------------------------------------
-- 1. 项目实施档案 5kcrm_work_profile（一对一，work_id 为主键）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `5kcrm_work_profile` (
  `work_id` INT(11) NOT NULL COMMENT '项目ID（主键，关联 5kcrm_work）',
  `project_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '项目类型：自有产品/外包项目',
  `impl_level` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '实施等级：一级/二级/三级/四级',
  `stability_days` INT(11) NOT NULL DEFAULT 0 COMMENT '稳定期（天）',
  `acceptance_result` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '验收结果三档：完成良好/基本完成/需要改进',
  `acceptance_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '验收人ID',
  `acceptance_time` INT(11) NOT NULL DEFAULT 0 COMMENT '验收时间',
  `plan_start_time` INT(11) NOT NULL DEFAULT 0 COMMENT '计划开始时间',
  `plan_end_time` INT(11) NOT NULL DEFAULT 0 COMMENT '计划结束时间',
  `actual_start_time` INT(11) NOT NULL DEFAULT 0 COMMENT '实际开始时间',
  `actual_end_time` INT(11) NOT NULL DEFAULT 0 COMMENT '实际结束时间',
  `risk_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '风险与责任归因',
  `version` INT(11) NOT NULL DEFAULT 1 COMMENT '乐观锁版本号',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `update_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`work_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='项目实施档案（P1）';

-- -----------------------------------------------------------------------------
-- 2. 项目四类里程碑 5kcrm_work_milestone（一对多）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `5kcrm_work_milestone` (
  `milestone_id` INT(11) NOT NULL AUTO_INCREMENT,
  `work_id` INT(11) NOT NULL DEFAULT 0 COMMENT '项目ID',
  `milestone_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '里程碑类型：需求确认/开发完成/测试通过/上线交付',
  `name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '里程碑名称',
  `plan_time` INT(11) NOT NULL DEFAULT 0 COMMENT '计划时间',
  `actual_time` INT(11) NOT NULL DEFAULT 0 COMMENT '实际时间',
  `status` VARCHAR(20) NOT NULL DEFAULT '未开始' COMMENT '状态：未开始/进行中/已完成/已延期',
  `sort` INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `evidence_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '证据/说明',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`milestone_id`) USING BTREE,
  INDEX `idx_milestone_work`(`work_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='项目里程碑（P1）';

-- -----------------------------------------------------------------------------
-- 3. 项目成员贡献 5kcrm_work_member_contribution（一对多）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `5kcrm_work_member_contribution` (
  `contribution_id` INT(11) NOT NULL AUTO_INCREMENT,
  `work_id` INT(11) NOT NULL DEFAULT 0 COMMENT '项目ID',
  `user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '贡献人ID',
  `contribution_role` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '贡献角色',
  `on_site_days` DECIMAL(6,1) NOT NULL DEFAULT 0.0 COMMENT '现场人日',
  `start_time` INT(11) NOT NULL DEFAULT 0 COMMENT '开始时间',
  `end_time` INT(11) NOT NULL DEFAULT 0 COMMENT '结束时间',
  `evidence_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '证据/说明',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`contribution_id`) USING BTREE,
  INDEX `idx_contrib_work`(`work_id`) USING BTREE,
  INDEX `idx_contrib_user`(`user_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='项目成员贡献（P1）';

-- -----------------------------------------------------------------------------
-- 4. 项目知识链接 5kcrm_work_knowledge_link（一对多）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `5kcrm_work_knowledge_link` (
  `link_id` INT(11) NOT NULL AUTO_INCREMENT,
  `work_id` INT(11) NOT NULL DEFAULT 0 COMMENT '项目ID',
  `link_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '链接类型：目录/接口/业务规则/开发变更/上线模块/使用指导',
  `title` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '标题',
  `url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '链接地址',
  `owner_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT '维护人ID',
  `completeness_status` VARCHAR(20) NOT NULL DEFAULT '待补充' COMMENT '完整性：完整/待补充/缺失',
  `sort` INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`link_id`) USING BTREE,
  INDEX `idx_klink_work`(`work_id`) USING BTREE,
  INDEX `idx_klink_type`(`link_type`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='项目知识链接（P1）';

SELECT 'P1 forward migration applied' AS result;
