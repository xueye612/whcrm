-- perf_fact precheck (read-only, ASCII)
SELECT VERSION() AS db_version;
SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact';
SELECT DEFAULT_CHARACTER_SET_NAME AS charset FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE();
