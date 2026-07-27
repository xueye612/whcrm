-- P2 迁移后只读核验（不写入数据）
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('5kcrm_lead_raw_batch','5kcrm_lead_raw','5kcrm_lead_dedupe_log');
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('5kcrm_lead_raw','5kcrm_lead_raw_batch','5kcrm_lead_dedupe_log')
ORDER BY TABLE_NAME, INDEX_NAME;
SELECT COUNT(*) AS existing_leads FROM 5kcrm_crm_leads;
