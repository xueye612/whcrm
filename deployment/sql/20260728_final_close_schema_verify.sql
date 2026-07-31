-- ============================================================
-- 最终收口 schema verify（必须能明确失败，不能只输出查询结果）
-- 任一期望不符：以 SIGNAL/ERROR 输出并退出非 0
-- MySQL 5.7 不支持存储过程外的 SIGNAL；改用：
--   1) 用 SELECT 显式输出 verify_* 行及期望值；
--   2) 由 PHP/Shell runner 检查计数是否非 0 后决定退出码。
-- 任何 FAIL_* 计数非 0 即为失败。
-- ============================================================

-- 1) performance 新列存在
SELECT 'verify_perf_cols' AS step,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance'
       AND COLUMN_NAME IN ('quarterly_base','reference_amount')
  ) AS expected_2,
  2 AS wanted_2,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance'
       AND COLUMN_NAME IN ('quarterly_base','reference_amount')) = 2, 0, 1) AS FAIL_perf_cols;

-- 2) performance_adjust_audit 表存在
SELECT 'verify_paa_table' AS step,
  IF((SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_adjust_audit') = 1, 0, 1) AS FAIL_paa_table;

-- 3) ledger_quality_issue 表存在
SELECT 'verify_lqi_table' AS step,
  IF((SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_ledger_quality_issue') = 1, 0, 1) AS FAIL_lqi_table;

-- 4) responsibility_case 新列存在
SELECT 'verify_case_cols' AS step,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_responsibility_case'
       AND COLUMN_NAME IN ('evidence','reviewer_user_id','review_time','review_note')
  ) AS expected_4,
  4 AS wanted_4,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_responsibility_case'
       AND COLUMN_NAME IN ('evidence','reviewer_user_id','review_time','review_note')) = 4, 0, 1) AS FAIL_case_cols;

-- 5) reward_candidate.settle_time 列存在
SELECT 'verify_reward_settle_time' AS step,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate'
       AND COLUMN_NAME='settle_time') = 1, 0, 1) AS FAIL_reward_settle_time;

-- 6) responsibility_case.status 默认为中文'认定中'
SELECT 'verify_case_default' AS step,
  COLUMN_DEFAULT,
  IF(COLUMN_DEFAULT = '认定中', 0, 1) AS FAIL_case_default
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_responsibility_case' AND COLUMN_NAME='status';

SELECT 'verify_done' AS result,
  -- 汇总：所有 FAIL_* 应为 0
  (SELECT IFNULL(SUM(cnt), 0)
   FROM (
     SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance'
          AND COLUMN_NAME IN ('quarterly_base','reference_amount')) = 2, 0, 1) AS cnt
     UNION ALL
     SELECT IF((SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_adjust_audit') = 1, 0, 1)
     UNION ALL
     SELECT IF((SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_ledger_quality_issue') = 1, 0, 1)
     UNION ALL
     SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_responsibility_case'
          AND COLUMN_NAME IN ('evidence','reviewer_user_id','review_time','review_note')) = 4, 0, 1)
     UNION ALL
     SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate'
          AND COLUMN_NAME='settle_time') = 1, 0, 1)
   ) t
  ) AS total_failures;
