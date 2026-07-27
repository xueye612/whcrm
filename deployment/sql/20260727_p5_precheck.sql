-- =============================================================================
-- P5 奖励候选与商务费用：迁移前只读预检（不执行任何 DDL/DML）
-- 兼容 MySQL 5.7。
-- =============================================================================
SELECT VERSION() AS db_version;

-- 1. 待新建表是否已存在
SELECT TABLE_NAME, TABLE_ROWS, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_reward_candidate','5kcrm_reward_batch','5kcrm_reward_offset','5kcrm_business_expense');

-- 2. 关键索引是否齐备
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_reward_candidate','5kcrm_reward_offset')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 3. 依赖：奖励配置表（pconfig，金额/上限"待配置"来源）是否存在
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_config';

-- 4. 字符集确认
SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE();

-- 5. 潜在冲突：同名表但非 P5 注释（应为 0 行）
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_reward_candidate','5kcrm_reward_batch','5kcrm_reward_offset','5kcrm_business_expense')
  AND TABLE_COMMENT NOT LIKE '%P5%';
