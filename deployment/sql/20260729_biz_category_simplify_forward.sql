-- =====================================================================
-- 20260729_biz_category_simplify_forward.sql
-- 幂等迁移：将历史商机类别简化为 direct/agent
-- 
-- 规则：
--   - dealer_customer_id > 0 的商机 -> business_category='agent', signing_method='dealer_signed'
--   - dealer_customer_id = 0 或 NULL 的商机 -> business_category='direct', signing_method='company_direct'
--   - 旧类别（dealer_dev/hospital_direct/hospital_agent/outsource）统一映射为 direct 或 agent
--   - 不修改 type_id（状态组保持不变）
--   - 不删除任何历史数据
-- =====================================================================

-- 1. 根据经销商推导代理类别（有经销商 = agent）
UPDATE `5kcrm_crm_business`
SET `business_category` = 'agent',
    `signing_method` = 'dealer_signed'
WHERE `dealer_customer_id` IS NOT NULL
  AND `dealer_customer_id` > 0
  AND (`business_category` IS NULL OR `business_category` NOT IN ('agent'));

-- 2. 无经销商 = 直签
UPDATE `5kcrm_crm_business`
SET `business_category` = 'direct',
    `signing_method` = 'company_direct'
WHERE (`dealer_customer_id` IS NULL OR `dealer_customer_id` = 0)
  AND (`business_category` IS NULL OR `business_category` NOT IN ('direct'));

-- 3. 旧类别兼容映射（仅更新仍为旧值的记录）
-- dealer_dev -> direct (直签)
UPDATE `5kcrm_crm_business`
SET `business_category` = 'direct'
WHERE `business_category` = 'dealer_dev';

-- hospital_direct -> direct (直签)
UPDATE `5kcrm_crm_business`
SET `business_category` = 'direct'
WHERE `business_category` = 'hospital_direct';

-- hospital_agent -> agent (代理)
UPDATE `5kcrm_crm_business`
SET `business_category` = 'agent'
WHERE `business_category` = 'hospital_agent';

-- outsource -> direct (直签)
UPDATE `5kcrm_crm_business`
SET `business_category` = 'direct'
WHERE `business_category` = 'outsource';

-- 4. 确保 signing_method 与 business_category 一致
UPDATE `5kcrm_crm_business`
SET `signing_method` = 'dealer_signed'
WHERE `business_category` = 'agent'
  AND (`signing_method` IS NULL OR `signing_method` <> 'dealer_signed');

UPDATE `5kcrm_crm_business`
SET `signing_method` = 'company_direct'
WHERE `business_category` = 'direct'
  AND (`signing_method` IS NULL OR `signing_method` <> 'company_direct');

-- 5. 确保经销商不能等于商机客户（数据修正）
UPDATE `5kcrm_crm_business`
SET `dealer_customer_id` = 0,
    `business_category` = 'direct',
    `signing_method` = 'company_direct'
WHERE `dealer_customer_id` = `customer_id`
  AND `dealer_customer_id` > 0;

SELECT '20260729_biz_category_simplify_forward completed' AS result;
