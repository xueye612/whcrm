-- ============================================================
-- 第六段 verify：确认规则补齐且不重复；严格幂等断言
-- ============================================================

SELECT 'verify_total_rows' AS step, COUNT(*) AS actual, 15 AS expected,
  IF(COUNT(*) = 15, 0, 1) AS FAIL_total_rows
FROM 5kcrm_business_stage_reward_rule;

SELECT 'verify_dup_rules' AS step, type_id, status_id, COUNT(*) AS cnt,
  IF((SELECT COUNT(*) FROM (
      SELECT type_id, status_id FROM 5kcrm_business_stage_reward_rule
      GROUP BY type_id, status_id HAVING COUNT(*)>1
  ) t) = 0, 0, 1) AS FAIL_dup_rules
FROM 5kcrm_business_stage_reward_rule
GROUP BY type_id, status_id
HAVING COUNT(*)>1;

SELECT 'verify_rules' AS step;
SELECT rule_id, type_id, status_id, source_type, amount, rules_version, is_enabled, update_time
FROM 5kcrm_business_stage_reward_rule
ORDER BY type_id, status_id;

SELECT 'verify_done' AS result,
  (SELECT IFNULL(SUM(cnt),0) FROM (
     SELECT IF((SELECT COUNT(*) FROM 5kcrm_business_stage_reward_rule) = 15, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM (SELECT type_id, status_id FROM 5kcrm_business_stage_reward_rule GROUP BY type_id, status_id HAVING COUNT(*)>1) t) = 0, 0, 1)
  ) t) AS total_failures;
