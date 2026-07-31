-- biz_reward_rule verify (read-only, ASCII)
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_business_stage_reward_rule';
SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_business_stage_reward_rule' AND INDEX_NAME='uk_type_status';
