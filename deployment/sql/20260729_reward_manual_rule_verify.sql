-- 验证：reward_manual_rule 表和列存在
SELECT
  IF(COUNT(*) > 0, 'PASS_manual_rule_table', 'FAIL_manual_rule_table') AS verify_1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_manual_rule';

SELECT
  IF(COUNT(*) > 0, 'PASS_manual_rule_id_col', 'FAIL_manual_rule_id_col') AS verify_2
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate' AND COLUMN_NAME = 'manual_rule_id';

SELECT
  IF(COUNT(*) > 0, 'PASS_audit_table', 'FAIL_audit_table') AS verify_3
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_candidate_audit';
