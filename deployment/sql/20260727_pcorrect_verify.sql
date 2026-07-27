-- pcorrect verify (read-only)
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('5kcrm_policy_approval','5kcrm_stage_offset','5kcrm_payment_tracking');
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_stage_offset' AND INDEX_NAME='uk_so_batch_user_project';
