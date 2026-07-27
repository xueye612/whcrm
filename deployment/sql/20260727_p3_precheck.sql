-- =============================================================================
-- P3 行业机会：迁移前只读预检（不执行任何 DDL/DML）
-- 兼容 MySQL 5.7。
-- =============================================================================
SELECT VERSION() AS db_version;

-- 1. 待新建表是否已存在
SELECT TABLE_NAME, TABLE_ROWS, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_opportunity','5kcrm_opportunity_stage');

-- 2. 关键唯一索引 uk_opp_stage（同机会同阶段防重复奖励）是否已存在
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_opportunity_stage'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- 3. 依赖的既有表（可关联客户）存在性
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_crm_customer';

-- 4. 字符集确认
SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE();

-- 5. 潜在冲突：同名表但非 P3 注释（应为 0 行）
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_opportunity','5kcrm_opportunity_stage')
  AND TABLE_COMMENT NOT LIKE '%P3%';
