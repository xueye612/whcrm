-- =============================================================================
-- P6 季度绩效：迁移前只读预检（不执行任何 DDL/DML）
-- 兼容 MySQL 5.7。
-- =============================================================================
SELECT VERSION() AS db_version;

-- 1. 待新建表是否已存在
SELECT TABLE_NAME, TABLE_ROWS, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_performance','5kcrm_responsibility_case');

-- 2. 关键索引是否齐备
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_performance','5kcrm_responsibility_case')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 3. 依赖：员工表（user_id 来源）存在性
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_admin_user';
SELECT COUNT(*) AS admin_users FROM 5kcrm_admin_user;

-- 4. 字符集确认
SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE();

-- 5. 潜在冲突：同名表但非 P6 注释（应为 0 行）
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_performance','5kcrm_responsibility_case')
  AND TABLE_COMMENT NOT LIKE '%P6%';
