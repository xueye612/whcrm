-- 20260730_stage_reward_config_precheck.sql
-- 执行前检查：确认依赖表存在、确认无冲突
SELECT 'precheck_start' AS step;

-- 1) business_stage_reward_rule 表必须存在（本迁移对其加列、回填）
SELECT
  (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_business_stage_reward_rule') AS reward_rule_table_exists,
  (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business_type') AS biz_type_table_exists,
  (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business_status') AS biz_status_table_exists;

-- 2) 确认 update_user_id 列当前是否存在（用于判断是否需要加列）
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_business_stage_reward_rule' AND COLUMN_NAME='update_user_id') AS update_user_id_col_exists;

-- 3) type_id 100/101 是否已被其他业务类别占用（冲突阻断）
SELECT
  (SELECT COUNT(*) FROM `5kcrm_crm_business_type` WHERE type_id=100 AND business_category IS NOT NULL AND business_category<>'direct') AS type100_conflict,
  (SELECT COUNT(*) FROM `5kcrm_crm_business_type` WHERE type_id=101 AND business_category IS NOT NULL AND business_category<>'agent') AS type101_conflict;

-- 4) 当前规则中 rule_name 为空的数量（回填对象）
SELECT COUNT(*) AS rules_with_empty_name
FROM `5kcrm_business_stage_reward_rule`
WHERE rule_name IS NULL OR rule_name = '';

SELECT 'precheck_done' AS step;
