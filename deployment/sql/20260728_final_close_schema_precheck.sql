-- ============================================================
-- 最终收口 schema precheck（只读，ASCII）
-- 校验目标表/列存在性，确认 forward 是否安全可执行
-- ============================================================

SELECT 'precheck_perf_table' AS step, TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance';

SELECT 'precheck_perf_cols' AS step, COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance'
  AND COLUMN_NAME IN ('quarterly_base','reference_amount');

SELECT 'precheck_paa_table' AS step, TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_adjust_audit';

SELECT 'precheck_lqi_table' AS step, TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_ledger_quality_issue';

SELECT 'precheck_case_cols' AS step, COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_responsibility_case'
  AND COLUMN_NAME IN ('evidence','reviewer_user_id','review_time','review_note');

SELECT 'precheck_reward_settle_time' AS step, COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_candidate'
  AND COLUMN_NAME='settle_time';

SELECT 'precheck_done' AS result;
