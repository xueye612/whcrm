-- =====================================================================
-- 20260729_biz_status_group_reorg_precheck.sql
-- 预检查：商机状态组重组前的环境验证
-- 必须在所有 20260728 旧迁移执行完毕后运行
-- 使用高 ID（type_id>=100, status_id>=1000）避免与旧迁移冲突
-- =====================================================================

-- 检查表是否存在
SELECT
  IF(COUNT(*) > 0, 'OK_type_table', 'FAIL_type_table') AS check_1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_crm_business_type';

SELECT
  IF(COUNT(*) > 0, 'OK_status_table', 'FAIL_status_table') AS check_2
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_crm_business_status';

SELECT
  IF(COUNT(*) > 0, 'OK_reward_rule_table', 'FAIL_reward_rule_table') AS check_3
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_business_stage_reward_rule';

-- 检查系统默认组 type_id=1 必须存在
SELECT
  IF(COUNT(*) > 0, 'OK_system_default', 'FAIL_system_default') AS check_4
FROM `5kcrm_crm_business_type` WHERE type_id = 1;

-- 检查 type_id=100 是否已被占用
SELECT
  IF(COUNT(*) = 0, 'OK_type_100_free', 'FAIL_type_100_occupied') AS check_5
FROM `5kcrm_crm_business_type` WHERE type_id = 100;

-- 检查 type_id=101 是否已被占用
SELECT
  IF(COUNT(*) = 0, 'OK_type_101_free', 'FAIL_type_101_occupied') AS check_6
FROM `5kcrm_crm_business_type` WHERE type_id = 101;

-- 检查 status_id 1000-1007 是否已被占用
SELECT
  IF(COUNT(*) = 0, 'OK_status_1000_1007_free', 'FAIL_status_1000_1007_occupied') AS check_7
FROM `5kcrm_crm_business_status` WHERE status_id BETWEEN 1000 AND 1007;

-- 检查 direct 是否已存在于其他 type_id（business_category 唯一索引）
SELECT
  IF(COUNT(*) = 0, 'OK_direct_not_exists', 'OK_direct_already_exists') AS check_8
FROM `5kcrm_crm_business_type` WHERE business_category = 'direct' AND is_display = 1;

-- 检查 agent 是否已存在于其他 type_id
SELECT
  IF(COUNT(*) = 0, 'OK_agent_not_exists', 'OK_agent_already_exists') AS check_9
FROM `5kcrm_crm_business_type` WHERE business_category = 'agent' AND is_display = 1;

-- 检查 business_category 唯一索引是否存在
SELECT
  IF(COUNT(*) > 0, 'OK_uk_business_category', 'WARN_no_uk_business_category') AS check_10
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_crm_business_type' AND INDEX_NAME = 'uk_business_category';

-- 检查现有有效奖励规则（按 order_id 分组，用于复制到新组）
-- 输出待人工确认项：如果同一 order_id 有多个不同金额的规则，需要人工确认
SELECT old_t.name AS old_group, s.order_id, s.name AS stage_name,
       COUNT(DISTINCT r.amount) AS distinct_amounts,
       GROUP_CONCAT(DISTINCT r.amount) AS amounts
FROM `5kcrm_business_stage_reward_rule` r
JOIN `5kcrm_crm_business_status` s ON r.status_id = s.status_id
JOIN `5kcrm_crm_business_type` old_t ON r.type_id = old_t.type_id
WHERE r.is_enabled = 1 AND old_t.is_display = 0
GROUP BY s.order_id, s.name
ORDER BY s.order_id;
