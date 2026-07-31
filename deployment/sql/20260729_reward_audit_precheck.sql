-- =====================================================================
-- 20260729_reward_audit_precheck.sql
-- 预检查：reward_candidate_audit 表是否已存在
-- =====================================================================

SELECT
  IF(COUNT(*) = 0, 'OK_audit_table_not_exists_will_create', 'OK_audit_table_already_exists') AS check_1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate_audit';

-- 检查 business_stage_reward_rule 表是否存在（规则管理依赖）
SELECT
  IF(COUNT(*) > 0, 'OK_rule_table_exists', 'FAIL_rule_table_missing') AS check_2
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule';
