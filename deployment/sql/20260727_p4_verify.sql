-- P4 核验 + 回滚说明
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('5kcrm_outsource_project','5kcrm_reward_distribution');
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('5kcrm_outsource_project','5kcrm_reward_distribution') ORDER BY TABLE_NAME,INDEX_NAME;
-- 回滚（停止P4代码后手动）：DROP TABLE IF EXISTS 5kcrm_reward_distribution; DROP TABLE IF EXISTS 5kcrm_outsource_project;
