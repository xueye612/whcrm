-- CRM arch precheck (read-only, ASCII only)
SELECT VERSION() AS db_version;
-- Check waste table row counts (must be 0 before drop)
SELECT '5kcrm_lead_raw' AS tbl, (SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_lead_raw') AS cnt
UNION ALL SELECT '5kcrm_lead_raw_batch', (SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_lead_raw_batch')
UNION ALL SELECT '5kcrm_lead_dedupe_log', (SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_lead_dedupe_log')
UNION ALL SELECT '5kcrm_opportunity', (SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_opportunity')
UNION ALL SELECT '5kcrm_opportunity_stage', (SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_opportunity_stage');
-- Check old indexes
SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME IN ('uk_source_ref','uk_receivables_id');
-- Check target columns
SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND (
  (TABLE_NAME='5kcrm_crm_business' AND COLUMN_NAME IN ('business_category','signing_method','dealer_customer_id'))
  OR (TABLE_NAME='5kcrm_reward_candidate' AND COLUMN_NAME IN ('occurred_time','business_id','contract_id','customer_id'))
  OR (TABLE_NAME='5kcrm_finance_record' AND COLUMN_NAME IN ('receivables_id','rel_type'))
);
SELECT DEFAULT_CHARACTER_SET_NAME AS charset FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE();
