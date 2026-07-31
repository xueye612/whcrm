-- CRM arch verify (read-only, ASCII only)
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME IN ('5kcrm_lead_raw','5kcrm_lead_raw_batch','5kcrm_lead_dedupe_log','5kcrm_opportunity','5kcrm_opportunity_stage');
SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='5kcrm_crm_business' AND COLUMN_NAME IN ('business_category','signing_method','dealer_customer_id');
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_customer_dealer_rel';
SELECT name FROM 5kcrm_crm_business_type WHERE name IN ('dealer_dev','hospital_direct','hospital_agent','outsource');
SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='5kcrm_reward_candidate' AND COLUMN_NAME IN ('occurred_time','business_id','contract_id','customer_id');
SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='5kcrm_reward_candidate' AND INDEX_NAME='uk_source_type_ref';
SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='5kcrm_finance_record' AND COLUMN_NAME IN ('receivables_id','rel_type');
SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='5kcrm_finance_record' AND INDEX_NAME='uk_recv_reltype';
