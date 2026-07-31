-- =====================================================================
-- 20260729_reward_audit_verify.sql
-- 验证：审计表和规则扩展列是否存在
-- =====================================================================

SELECT IF(COUNT(*) > 0, 'PASS_audit_table', 'FAIL_audit_table') AS verify_1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate_audit';

SELECT IF(COUNT(*) > 0, 'PASS_rule_name', 'FAIL_rule_name') AS verify_2
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'rule_name';

SELECT IF(COUNT(*) > 0, 'PASS_direction', 'FAIL_direction') AS verify_3
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'direction';

SELECT IF(COUNT(*) > 0, 'PASS_calc_method', 'FAIL_calc_method') AS verify_4
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'calc_method';

SELECT IF(COUNT(*) > 0, 'PASS_monthly_cap', 'FAIL_monthly_cap') AS verify_5
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule' AND COLUMN_NAME = 'monthly_cap';

SELECT 'verify complete' AS result;
