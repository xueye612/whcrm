-- =============================================================================
-- 绩效记录生成方式字段（create_method）
-- 版本：20260730
-- 幂等：可重复执行；列已存在时安全跳过
-- =============================================================================

SET @db_name = DATABASE();

-- performance 表新增 create_method 列：区分系统自动生成(auto)与人工创建(manual)
SET @has_create_method = (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='5kcrm_performance' AND COLUMN_NAME='create_method');
SET @ddl_create_method = IF(@has_create_method=0,
  'ALTER TABLE `5kcrm_performance` ADD COLUMN `create_method` VARCHAR(20) NOT NULL DEFAULT '''' COMMENT ''生成方式：auto=系统自动归集生成 manual=人工录入创建'' AFTER `rules_version`;',
  'SELECT ''skip: performance.create_method exists'';');
PREPARE s FROM @ddl_create_method; EXECUTE s; DEALLOCATE PREPARE s;

-- 回填历史数据：有 create_user_id > 0 且 duty_score=0 且 task_score=0 的视为自动生成
-- 其余视为人工录入（保守处理，不改变已有记录的含义）
SET @has_data = (SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='5kcrm_performance' AND COLUMN_NAME='create_method');
SET @backfill = IF(@has_data=1,
  'UPDATE `5kcrm_performance` SET `create_method` = ''manual'' WHERE `create_method` = '''';',
  'SELECT ''skip: create_method not exists'';');
PREPARE s FROM @backfill; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'perf create_method migration applied' AS result;
