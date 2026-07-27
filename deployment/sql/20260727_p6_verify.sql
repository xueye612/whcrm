-- P6 核验 + 回滚说明
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('5kcrm_performance','5kcrm_responsibility_case');
-- 回滚（停止P6代码后手动）：DROP TABLE IF EXISTS 5kcrm_responsibility_case; DROP TABLE IF EXISTS 5kcrm_performance;
