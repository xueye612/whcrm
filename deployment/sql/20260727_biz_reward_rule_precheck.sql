-- biz_reward_rule precheck (read-only, ASCII)
SELECT VERSION() AS db_version;
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_business_stage_reward_rule';
