-- =====================================================================
-- 20260730_stage_reward_config_forward.sql
-- 商机阶段奖励配置化迁移（幂等；MySQL 5.7 兼容）
-- 目标：
--   1) business_stage_reward_rule 增加 update_user_id（记录最后修改人）
--   2) 新建 reward_rule_audit 表（规则变更审计：操作人/时间/前后内容）
--   3) 回填规则 rule_name 为空者（取 类型名-阶段名），消除来源不透明
--   4) 确保默认「直签/代理签约」类型与阶段存在（新环境可直接使用，不依赖手工SQL）
--   5) 为默认类型补齐阶段奖励规则（仅在缺失时补，重复执行不产生重复）
-- 严格幂等：重复执行不产生重复数据、不覆盖已存在配置、不写无意义 update_time。
-- 历史保护：reward_candidate 已在创建时保存 amount/rules_version/rule_id 快照，
--   本迁移不触碰任何已生成奖励候选，修改规则金额只影响未来推进。
-- =====================================================================

-- ========== 0. 前置信息 ==========
SELECT 'stage_reward_config_start' AS step;

-- ========== 1. business_stage_reward_rule 增加 update_user_id ==========
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '5kcrm_business_stage_reward_rule'
    AND COLUMN_NAME = 'update_user_id'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `5kcrm_business_stage_reward_rule` ADD COLUMN `update_user_id` INT(11) NOT NULL DEFAULT 0 COMMENT ''最后修改人'' AFTER `update_time`',
  'SELECT ''update_user_id_already_exists'' AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========== 2. 新建 reward_rule_audit 审计表 ==========
CREATE TABLE IF NOT EXISTS `5kcrm_reward_rule_audit` (
  `audit_id` INT(11) NOT NULL AUTO_INCREMENT,
  `rule_id` INT(11) NOT NULL DEFAULT 0 COMMENT '规则ID',
  `operation_type` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'create/update/enable/disable/delete/disable_on_delete',
  `old_data_json` TEXT COMMENT '变更前',
  `new_data_json` TEXT COMMENT '变更后',
  `change_reason` VARCHAR(500) NOT NULL DEFAULT '',
  `operator_user_id` INT(11) NOT NULL DEFAULT 0,
  `operator_name` VARCHAR(64) NOT NULL DEFAULT '',
  `operation_time` INT(11) NOT NULL DEFAULT 0,
  `request_ip` VARCHAR(64) NOT NULL DEFAULT '',
  `create_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`audit_id`) USING BTREE,
  KEY `idx_rule_id`(`rule_id`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='阶段奖励规则变更审计';

-- ========== 3. 确保默认「直签/代理签约」类型与阶段存在（新环境可用） ==========
-- 直签组 type_id=100
INSERT INTO `5kcrm_crm_business_type` (type_id, name, structure_id, status, is_display, business_category, create_user_id, create_time, update_time)
SELECT 100, '直签', '', 1, 1, 'direct', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_type` WHERE type_id = 100);
-- 代理签约组 type_id=101
INSERT INTO `5kcrm_crm_business_type` (type_id, name, structure_id, status, is_display, business_category, create_user_id, create_time, update_time)
SELECT 101, '代理签约', '', 1, 1, 'agent', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_type` WHERE type_id = 101);

-- 直签阶段 status_id 1000-1003
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1000, 100, '基础核实', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 1000);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1001, 100, '有效联系', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 1001);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1002, 100, '正式交流', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 1002);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1003, 100, '明确项目', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 1003);

-- 代理签约阶段 status_id 1004-1007
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1004, 101, '基础核实', 0, 1 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 1004);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1005, 101, '有效联系', 0, 2 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 1005);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1006, 101, '正式交流', 0, 3 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 1006);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1007, 101, '明确项目', 0, 4 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 1007);

-- 终态阶段（共享，赢单/输单/无效，order_id>=99）确保存在
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1, 1, '赢单', 100, 99 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 1);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 2, 1, '输单', 0, 98 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 2);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 3, 1, '无效', 0, 97 WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id = 3);

-- ========== 4. 写入 crm_config 默认类型ID（业务代码 getTypeIdByCategory 依赖） ==========
INSERT INTO `5kcrm_crm_config` (`name`, `value`, `description`)
SELECT 'business_type_id_direct', '100', '直签状态组type_id'
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_config` WHERE `name` = 'business_type_id_direct');
INSERT INTO `5kcrm_crm_config` (`name`, `value`, `description`)
SELECT 'business_type_id_agent', '101', '代理签约状态组type_id'
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_config` WHERE `name` = 'business_type_id_agent');

-- ========== 5. 为默认类型补齐阶段奖励规则（仅在缺失时补） ==========
-- 直签 type_id=100：基础核实50 / 有效联系300 / 正式交流800 / 明确项目1500
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
SELECT 100, 1000, '商机阶段奖励', 50.00, 'v1', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_business_stage_reward_rule` WHERE type_id = 100 AND status_id = 1000);
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
SELECT 100, 1001, '商机阶段奖励', 300.00, 'v1', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_business_stage_reward_rule` WHERE type_id = 100 AND status_id = 1001);
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
SELECT 100, 1002, '商机阶段奖励', 800.00, 'v1', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_business_stage_reward_rule` WHERE type_id = 100 AND status_id = 1002);
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
SELECT 100, 1003, '商机阶段奖励', 1500.00, 'v1', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_business_stage_reward_rule` WHERE type_id = 100 AND status_id = 1003);

-- 代理签约 type_id=101：与直签同阶段同金额
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
SELECT 101, 1004, '商机阶段奖励', 50.00, 'v1', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_business_stage_reward_rule` WHERE type_id = 101 AND status_id = 1004);
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
SELECT 101, 1005, '商机阶段奖励', 300.00, 'v1', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_business_stage_reward_rule` WHERE type_id = 101 AND status_id = 1005);
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
SELECT 101, 1006, '商机阶段奖励', 800.00, 'v1', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_business_stage_reward_rule` WHERE type_id = 101 AND status_id = 1006);
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
SELECT 101, 1007, '商机阶段奖励', 1500.00, 'v1', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_business_stage_reward_rule` WHERE type_id = 101 AND status_id = 1007);

-- ========== 6. 回填 rule_name 为空的规则（取 类型名-阶段名），幂等 ==========
UPDATE `5kcrm_business_stage_reward_rule` r
JOIN `5kcrm_crm_business_type` t ON r.type_id = t.type_id
JOIN `5kcrm_crm_business_status` s ON r.status_id = s.status_id
SET r.rule_name = CONCAT(t.name, '-', s.name)
WHERE (r.rule_name IS NULL OR r.rule_name = '');

-- 同时回填 auto_generate 缺省（旧规则可能为 NULL）
UPDATE `5kcrm_business_stage_reward_rule` SET auto_generate = 1 WHERE auto_generate IS NULL;
UPDATE `5kcrm_business_stage_reward_rule` SET need_review = 1 WHERE need_review IS NULL;
UPDATE `5kcrm_business_stage_reward_rule` SET calc_method = 'fixed' WHERE calc_method IS NULL OR calc_method = '';
UPDATE `5kcrm_business_stage_reward_rule` SET direction = 'reward' WHERE direction IS NULL OR direction = '';

SELECT '20260730_stage_reward_config_forward completed' AS result;
