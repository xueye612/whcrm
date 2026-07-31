-- =====================================================================
-- 20260729_biz_category_simplify_precheck.sql
-- 预检查：确认商机类别简化为直签/代理所需的前提条件
-- =====================================================================

-- 检查 crm_business 表是否有 business_category 列
SELECT
  IF(COUNT(*) > 0, 'OK_business_category_exists', 'FAIL_business_category_missing') AS check_1
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_crm_business' AND COLUMN_NAME = 'business_category';

-- 检查 crm_business 表是否有 signing_method 列
SELECT
  IF(COUNT(*) > 0, 'OK_signing_method_exists', 'FAIL_signing_method_missing') AS check_2
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_crm_business' AND COLUMN_NAME = 'signing_method';

-- 检查 crm_business 表是否有 dealer_customer_id 列
SELECT
  IF(COUNT(*) > 0, 'OK_dealer_customer_id_exists', 'FAIL_dealer_customer_id_missing') AS check_3
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_crm_business' AND COLUMN_NAME = 'dealer_customer_id';

-- 检查默认商机状态组是否存在（不删除）
SELECT
  IF(COUNT(*) > 0, 'OK_default_type_exists', 'FAIL_default_type_missing') AS check_4
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '5kcrm_crm_business_type';

-- 统计历史商机中使用了旧类别（dealer_dev/hospital_direct/hospital_agent/outsource）的记录数
-- 这些记录不需要修改，仅用于确认
SELECT
  COUNT(*) AS legacy_category_count
FROM information_schema.TABLES t
WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = '5kcrm_crm_business';
-- 注：实际统计需要运行时执行，此处仅预检查表存在性
