-- P3 核验 + 回滚说明
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('5kcrm_opportunity','5kcrm_opportunity_stage');
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_opportunity_stage' ORDER BY INDEX_NAME;
-- 回滚（停止P3代码后手动执行，不自动跑）：
--   DROP TABLE IF EXISTS 5kcrm_opportunity_stage;
--   DROP TABLE IF EXISTS 5kcrm_opportunity;
