-- =====================================================================
-- 20260729_biz_field_hide_verify.sql
-- 验证：确认重复字段已隐藏，新增商机页面只会得到一个经销商业务字段
-- =====================================================================

-- 验证重复字段已隐藏
SELECT
  IF(COUNT(*) = 0, 'PASS_no_visible_duplicate_fields', CONCAT('FAIL_visible_duplicates: ', COUNT(*))) AS verify_1
FROM `5kcrm_admin_field`
WHERE `types` = 'crm_business'
  AND `field` IN ('signing_method', 'business_category', 'dealer_customer_id', 'crm_rianjp')
  AND `is_display` = 1;

-- 验证新增商机页面可见字段中不包含重复字段
SELECT
  `field`, `name`, `is_display`
FROM `5kcrm_admin_field`
WHERE `types` = 'crm_business'
  AND `field` IN ('signing_method', 'business_category', 'dealer_customer_id', 'crm_rianjp');

SELECT 'verify complete' AS result;
