-- pconfig 迁移后只读核验（不写入数据）
-- 1. 配置表存在
SELECT TABLE_NAME, TABLE_COMMENT FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_config';
-- 2. 4 个未确认配置项已初始化（值 NULL=待配置）
SELECT config_key, (CASE WHEN config_value IS NULL OR config_value='' THEN '待配置' ELSE config_value END) AS state, config_desc
FROM 5kcrm_reward_config
WHERE config_key IN ('monthly_cap_amount','dealer_first_payment_reward','hospital_stage_rewards','outsource_pool_split')
ORDER BY config_key;
-- 3. 主键存在
SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_reward_config' AND INDEX_NAME='PRIMARY';
