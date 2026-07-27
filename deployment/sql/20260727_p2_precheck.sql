-- =============================================================================
-- P2 原始数据/有效线索：迁移前只读预检（不执行任何 DDL/DML）
-- 目标：核验 MySQL 版本、目标表/索引是否已存在、依赖表与数据量、潜在冲突。
-- 兼容 MySQL 5.7。
-- =============================================================================
SELECT VERSION() AS db_version;

-- 1. 待新建表是否已存在（CREATE TABLE IF NOT EXISTS 已兜底，重复执行安全）
SELECT TABLE_NAME, TABLE_ROWS, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_lead_raw_batch','5kcrm_lead_raw','5kcrm_lead_dedupe_log');

-- 2. 已存在表则检查关键索引是否齐备
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_lead_raw','5kcrm_lead_raw_batch','5kcrm_lead_dedupe_log')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 3. 依赖的既有线索表（归并目标）存在性与数据量
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_crm_leads';

SELECT COUNT(*) AS existing_leads FROM 5kcrm_crm_leads;

-- 4. 字符集确认
SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE();

-- 5. 潜在冲突：确认目标表名不与既有业务表重名（应为 0 行）
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_lead_raw_batch','5kcrm_lead_raw','5kcrm_lead_dedupe_log')
  AND TABLE_COMMENT NOT LIKE '%P2%';
