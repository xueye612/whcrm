-- 20260730_stage_reward_config_verify.sql
-- 执行后验证：表结构、唯一性、幂等、默认配置、历史保护
SELECT 'verify_start' AS step;

-- 1) update_user_id 列已存在
SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_business_stage_reward_rule' AND COLUMN_NAME='update_user_id') = 1,
  0, 1) AS FAIL_missing_update_user_id;

-- 2) reward_rule_audit 表已创建
SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_rule_audit') = 1,
  0, 1) AS FAIL_missing_audit_table;

-- 3) 默认类型与阶段存在（新环境可用）
SELECT IF(
  (SELECT COUNT(*) FROM `5kcrm_crm_business_type` WHERE type_id IN (100,101) AND is_display=1) = 2,
  0, 1) AS FAIL_default_types_missing;
SELECT IF(
  (SELECT COUNT(*) FROM `5kcrm_crm_business_status` WHERE type_id=100 AND status_id IN (1000,1001,1002,1003)) = 4,
  0, 1) AS FAIL_direct_stages_missing;
SELECT IF(
  (SELECT COUNT(*) FROM `5kcrm_crm_business_status` WHERE type_id=101 AND status_id IN (1004,1005,1006,1007)) = 4,
  0, 1) AS FAIL_agent_stages_missing;

-- 4) 默认阶段奖励规则存在
SELECT IF(
  (SELECT COUNT(*) FROM `5kcrm_business_stage_reward_rule` WHERE type_id=100 AND status_id IN (1000,1001,1002,1003)) = 4,
  0, 1) AS FAIL_direct_rules_missing;
SELECT IF(
  (SELECT COUNT(*) FROM `5kcrm_business_stage_reward_rule` WHERE type_id=101 AND status_id IN (1004,1005,1006,1007)) = 4,
  0, 1) AS FAIL_agent_rules_missing;

-- 5) 幂等性：同一 (type_id,status_id) 不应出现重复
SELECT IF(
  (SELECT COUNT(*) FROM (
     SELECT type_id, status_id FROM `5kcrm_business_stage_reward_rule` GROUP BY type_id, status_id HAVING COUNT(*) > 1
   ) dup) = 0, 0, 1) AS FAIL_duplicate_type_status;

-- 6) rule_name 回填：不存在空 rule_name（可关联到类型/阶段的）
SELECT IF(
  (SELECT COUNT(*) FROM `5kcrm_business_stage_reward_rule` r
     LEFT JOIN `5kcrm_crm_business_type` t ON r.type_id=t.type_id
     LEFT JOIN `5kcrm_crm_business_status` s ON r.status_id=s.status_id
     WHERE (r.rule_name IS NULL OR r.rule_name='') AND t.type_id IS NOT NULL AND s.status_id IS NOT NULL) = 0,
  0, 1) AS FAIL_empty_rule_name_after_backfill;

-- 7) 配置项写入
SELECT IF(
  (SELECT COUNT(*) FROM `5kcrm_crm_config` WHERE name='business_type_id_direct' AND value='100') = 1, 0, 1) AS FAIL_config_direct;
SELECT IF(
  (SELECT COUNT(*) FROM `5kcrm_crm_config` WHERE name='business_type_id_agent' AND value='101') = 1, 0, 1) AS FAIL_config_agent;

-- 8) 汇总失败计数（聚合上方关键检查）
SET @f_update_user := (SELECT IF(COUNT(*)=1,0,1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_business_stage_reward_rule' AND COLUMN_NAME='update_user_id');
SET @f_audit_tbl := (SELECT IF(COUNT(*)=1,0,1) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_rule_audit');
SET @f_dup := (SELECT IF(COUNT(*)=0,0,1) FROM (SELECT type_id,status_id FROM `5kcrm_business_stage_reward_rule` GROUP BY type_id,status_id HAVING COUNT(*)>1) d);
SELECT (@f_update_user + @f_audit_tbl + @f_dup) AS total_failures,
       @f_update_user AS fail_update_user_id, @f_audit_tbl AS fail_audit_table, @f_dup AS fail_duplicate;

-- 9) 总览：当前阶段奖励规则（按类型分组）
SELECT r.type_id, t.name AS type_name, t.is_display, COUNT(*) AS rule_count,
       SUM(IF(r.is_enabled=1,1,0)) AS enabled_count
FROM `5kcrm_business_stage_reward_rule` r
LEFT JOIN `5kcrm_crm_business_type` t ON r.type_id=t.type_id
GROUP BY r.type_id, t.name, t.is_display
ORDER BY t.is_display DESC, r.type_id ASC;

SELECT 'verify_done' AS step;
