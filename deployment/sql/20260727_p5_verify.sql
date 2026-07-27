-- P5 核验 + 回滚说明
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('5kcrm_reward_candidate','5kcrm_reward_batch','5kcrm_reward_offset','5kcrm_business_expense');
-- 回滚（停止P5代码后手动）：DROP TABLE IF EXISTS 5kcrm_business_expense; DROP TABLE IF EXISTS 5kcrm_reward_offset; DROP TABLE IF EXISTS 5kcrm_reward_batch; DROP TABLE IF EXISTS 5kcrm_reward_candidate;
