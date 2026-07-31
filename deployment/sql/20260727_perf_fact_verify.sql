-- perf_fact verify (read-only, ASCII)
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact';
SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact' AND INDEX_NAME IN ('uk_fact_source','idx_fact_perf','idx_fact_user_period');
SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact' AND COLUMN_NAME IN ('fact_id','perf_id','user_id','period','dimension','direction','fact_type','source_type','source_id','occurred_time','status','submit_user_id','reviewer_user_id');
