-- =============================================================================
-- P6 季度绩效：四权重事实汇总、质量三档、评级限制、本人回避、责任认定(独立、不自动扣款)
-- 幂等；utf8；不写入业务数据。
-- =============================================================================
CREATE TABLE IF NOT EXISTS `5kcrm_performance` (
  `perf_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `period` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '季度 如2026Q3',
  `duty_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '核心职责(权重40%)',
  `task_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '重点任务/项目(权重30%)',
  `quality_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '测试与质量(权重20%)',
  `collab_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '协作及CRM/飞书记录(权重10%)',
  `weighted_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '加权得分',
  `quality_tier` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '质量三档：完成良好/基本完成/需要改进',
  `rating` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '评级：优秀/合格/待改进',
  `rating_factor` DECIMAL(3,2) NOT NULL DEFAULT 1.00 COMMENT '评级系数 1.2/1.0/0.6',
  `reviewer_user_id` INT(11) NOT NULL DEFAULT 0,
  `review_time` INT(11) NOT NULL DEFAULT 0,
  `review_note` VARCHAR(500) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT '待确认' COMMENT '待确认/已确认',
  `rules_version` VARCHAR(20) NOT NULL DEFAULT 'v1',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`perf_id`) USING BTREE,
  INDEX `idx_perf_user_period`(`user_id`,`period`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='季度绩效（P6）';

CREATE TABLE IF NOT EXISTS `5kcrm_responsibility_case` (
  `case_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `period` VARCHAR(10) NOT NULL DEFAULT '',
  `title` VARCHAR(200) NOT NULL DEFAULT '',
  `severity` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '严重程度',
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT '认定中' COMMENT '认定中/已认定/已撤销',
  `create_user_id` INT(11) NOT NULL DEFAULT 0,
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`case_id`) USING BTREE,
  INDEX `idx_rcase_user`(`user_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='责任认定（P6，独立，不自动扣款）';

SELECT 'P6 forward migration applied' AS result;
