-- pcorrect precheck (read-only)
SELECT VERSION() AS db_version;
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('5kcrm_policy_approval','5kcrm_stage_offset','5kcrm_payment_tracking');
SELECT DEFAULT_CHARACTER_SET_NAME AS charset FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE();
