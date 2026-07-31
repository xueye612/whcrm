-- =====================================================================
-- 20260729_biz_field_hide_precheck.sql
-- 预检查：确认 admin_field 表中重复字段的当前状态
-- =====================================================================

-- 检查 crm_business 的系统字段中是否有 signing_method / business_category / dealer_customer_id
SELECT field, name, is_display, operating
FROM `5kcrm_admin_field`
WHERE types = 'crm_business'
  AND field IN ('signing_method', 'business_category', 'dealer_customer_id', 'crm_rianjp')
ORDER BY field;

-- 统计需要隐藏的字段数量
SELECT
  COUNT(*) AS fields_to_hide
FROM `5kcrm_admin_field`
WHERE types = 'crm_business'
  AND field IN ('signing_method', 'business_category', 'dealer_customer_id', 'crm_rianjp')
  AND is_display = 1;
