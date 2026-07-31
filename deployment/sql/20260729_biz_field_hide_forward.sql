-- =====================================================================
-- 20260729_biz_field_hide_forward.sql
-- 幂等迁移：将 crm_business 重复字段设为隐藏
-- 不删除字段定义和历史数据
-- =====================================================================

-- 隐藏重复字段（signing_method / business_category / dealer_customer_id / crm_rianjp）
-- 这些字段由经销商选择自动推导，不需要用户手动填写
UPDATE `5kcrm_admin_field`
SET `is_display` = 0
WHERE `types` = 'crm_business'
  AND `field` IN ('signing_method', 'business_category', 'dealer_customer_id', 'crm_rianjp')
  AND `is_display` = 1;

SELECT ROW_COUNT() AS fields_hidden, '20260729_biz_field_hide_forward completed' AS result;
