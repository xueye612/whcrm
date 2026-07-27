-- =============================================================================
-- pconfig 奖励/绩效可配置项：迁移前只读预检（不执行任何 DDL/DML，不在表不存在时报错）
-- 兼容 MySQL 5.7。
-- =============================================================================
SELECT VERSION() AS db_version;

-- 1. 配置表是否已存在（CREATE TABLE IF NOT EXISTS + INSERT IGNORE 已兜底，重复执行安全）
SELECT TABLE_NAME, TABLE_ROWS, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_config';

-- 2. 配置表列结构是否符合预期（仅 information_schema，不直接查询业务表）
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_config'
ORDER BY ORDINAL_POSITION;

-- 3. 字符集确认
SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE();

-- 4. 潜在冲突：同名表但非 pconfig 注释（应为 0 行）
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_reward_config'
  AND TABLE_COMMENT NOT LIKE '%奖励/绩效可配置项%';

-- 说明：4 个配置项(monthly_cap_amount/dealer_first_payment_reward/hospital_stage_rewards/outsource_pool_split)
--       由 forward 迁移 INSERT IGNORE 初始化为 NULL(=待配置)；其"待配置/已配置"状态以 verify 阶段为准。
