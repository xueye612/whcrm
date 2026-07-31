-- =====================================================================
-- 20260729_biz_status_group_reorg_verify.sql
-- 验证：检查新状态组、阶段、奖励规则和配置是否正确
-- =====================================================================

-- 验证直签组存在
SELECT
  IF(COUNT(*) > 0, 'PASS_direct_group', 'FAIL_direct_group') AS verify_1
FROM `5kcrm_crm_business_type`
WHERE type_id=100 AND business_category='direct' AND is_display=1;

-- 验证代理签约组存在
SELECT
  IF(COUNT(*) > 0, 'PASS_agent_group', 'FAIL_agent_group') AS verify_2
FROM `5kcrm_crm_business_type`
WHERE type_id=101 AND business_category='agent' AND is_display=1;

-- 验证直签组阶段数量
SELECT
  IF(COUNT(*) = 4, 'PASS_direct_stages', 'FAIL_direct_stages') AS verify_3
FROM `5kcrm_crm_business_status` WHERE type_id=100;

-- 验证代理签约组阶段数量
SELECT
  IF(COUNT(*) = 4, 'PASS_agent_stages', 'FAIL_agent_stages') AS verify_4
FROM `5kcrm_crm_business_status` WHERE type_id=101;

-- 验证旧组已隐藏
SELECT
  IF(SUM(is_display) = 0, 'PASS_old_groups_hidden', 'FAIL_old_groups_not_hidden') AS verify_5
FROM `5kcrm_crm_business_type` WHERE type_id IN (2,3,4,5);

-- 验证配置项存在
SELECT
  IF(COUNT(*) = 2, 'PASS_config', 'FAIL_config') AS verify_6
FROM `5kcrm_crm_config`
WHERE name IN ('business_type_id_direct', 'business_type_id_agent');

-- 验证直签组奖励规则
SELECT
  COUNT(*) AS direct_reward_rules,
  IF(COUNT(*) > 0, 'PASS_direct_reward', 'WARN_direct_reward_empty') AS verify_7
FROM `5kcrm_business_stage_reward_rule` WHERE type_id=100 AND is_enabled=1;

-- 验证代理签约组奖励规则
SELECT
  COUNT(*) AS agent_reward_rules,
  IF(COUNT(*) > 0, 'PASS_agent_reward', 'WARN_agent_reward_empty') AS verify_8
FROM `5kcrm_business_stage_reward_rule` WHERE type_id=101 AND is_enabled=1;

-- 验证系统默认组保留
SELECT
  IF(COUNT(*) > 0, 'PASS_system_default', 'FAIL_system_default') AS verify_9
FROM `5kcrm_crm_business_type` WHERE type_id=1;

SELECT 'verify complete' AS result;
