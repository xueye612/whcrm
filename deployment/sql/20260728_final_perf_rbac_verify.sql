-- ============================================================
-- 第七段 verify：绩效状态字典 + RBAC 子权限
-- 必须能明确失败；任何 FAIL_* != 0 即失败
-- ============================================================

SELECT 'verify_perf_default_status' AS step, COLUMN_DEFAULT,
  IF(COLUMN_DEFAULT = '待确认', 0, 1) AS FAIL_perf_default_status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance' AND COLUMN_NAME='status';

SELECT 'verify_fact_default_status' AS step, COLUMN_DEFAULT,
  IF(COLUMN_DEFAULT = '待审核', 0, 1) AS FAIL_fact_default_status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact' AND COLUMN_NAME='status';

SELECT 'verify_fact_default_direction' AS step, COLUMN_DEFAULT,
  IF(COLUMN_DEFAULT = '正向', 0, 1) AS FAIL_fact_default_direction
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact' AND COLUMN_NAME='direction';

SELECT 'verify_perf_rules_count' AS step, COUNT(*) AS actual, 11 AS expected,
  IF((SELECT COUNT(*) FROM 5kcrm_admin_rule WHERE name LIKE 'perf_%' AND pid=442) >= 11, 0, 1) AS FAIL_perf_rules_count
FROM 5kcrm_admin_rule WHERE name LIKE 'perf_%' AND pid=442;

SELECT 'verify_perf_rules' AS step;
SELECT id, pid, title, name, level, status FROM 5kcrm_admin_rule
WHERE name LIKE 'perf_%' AND pid=442 ORDER BY id;

SELECT 'verify_no_english_perf_status' AS step, COUNT(*) AS actual, 0 AS expected,
  IF((SELECT COUNT(*) FROM 5kcrm_performance WHERE status IN ('pending_confirmation','approved','returned')) = 0, 0, 1) AS FAIL_no_english_perf_status
FROM 5kcrm_performance WHERE status IN ('pending_confirmation','approved','returned');

SELECT 'verify_no_english_fact_status' AS step, COUNT(*) AS actual, 0 AS expected,
  IF((SELECT COUNT(*) FROM 5kcrm_performance_fact WHERE status IN ('pending_review','approved','rejected')) = 0, 0, 1) AS FAIL_no_english_fact_status
FROM 5kcrm_performance_fact WHERE status IN ('pending_review','approved','rejected');

SELECT 'verify_no_english_direction' AS step, COUNT(*) AS actual, 0 AS expected,
  IF((SELECT COUNT(*) FROM 5kcrm_performance_fact WHERE direction IN ('positive','negative')) = 0, 0, 1) AS FAIL_no_english_direction
FROM 5kcrm_performance_fact WHERE direction IN ('positive','negative');

SELECT 'verify_done' AS result,
  (SELECT IFNULL(SUM(cnt),0) FROM (
     SELECT IF((SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance' AND COLUMN_NAME='status') = '待确认', 0, 1)
     UNION ALL SELECT IF((SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact' AND COLUMN_NAME='status') = '待审核', 0, 1)
     UNION ALL SELECT IF((SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact' AND COLUMN_NAME='direction') = '正向', 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM 5kcrm_admin_rule WHERE name LIKE 'perf_%' AND pid=442) >= 11, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM 5kcrm_performance WHERE status IN ('pending_confirmation','approved','returned')) = 0, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM 5kcrm_performance_fact WHERE status IN ('pending_review','approved','rejected')) = 0, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM 5kcrm_performance_fact WHERE direction IN ('positive','negative')) = 0, 0, 1)
  ) t) AS total_failures;
