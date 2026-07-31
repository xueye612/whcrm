-- =====================================================================
-- 20260729_biz_category_simplify_verify.sql
-- 验证：检查商机类别已简化为 direct/agent
-- =====================================================================

-- 检查不存在旧类别记录
SELECT
  IF(COUNT(*) = 0, 'PASS_no_legacy_category', CONCAT('FAIL_legacy_remaining: ', COUNT(*))) AS verify_1
FROM `5kcrm_crm_business`
WHERE `business_category` IN ('dealer_dev', 'hospital_direct', 'hospital_agent', 'outsource');

-- 检查 dealer_customer_id = customer_id 的记录（应为0）
SELECT
  IF(COUNT(*) = 0, 'PASS_no_self_dealer', CONCAT('FAIL_self_dealer: ', COUNT(*))) AS verify_2
FROM `5kcrm_crm_business`
WHERE `dealer_customer_id` = `customer_id`
  AND `dealer_customer_id` > 0;

-- 检查 agent 类别的 signing_method 是否一致
SELECT
  IF(COUNT(*) = 0, 'PASS_agent_signing', CONCAT('FAIL_agent_signing: ', COUNT(*))) AS verify_3
FROM `5kcrm_crm_business`
WHERE `business_category` = 'agent'
  AND `signing_method` <> 'dealer_signed';

-- 检查 direct 类别的 signing_method 是否一致
SELECT
  IF(COUNT(*) = 0, 'PASS_direct_signing', CONCAT('FAIL_direct_signing: ', COUNT(*))) AS verify_4
FROM `5kcrm_crm_business`
WHERE `business_category` = 'direct'
  AND `signing_method` <> 'company_direct';

-- 类别分布统计
SELECT `business_category`, COUNT(*) AS cnt
FROM `5kcrm_crm_business`
GROUP BY `business_category`;

SELECT 'verify complete' AS result;
