-- ============================================================
-- 第二段 verify：确认 type 去重、中文化、business_category 唯一
-- 必须能明确失败：以 FAIL_* 计数输出；runner 检查任何 FAIL_* != 0 即失败
-- ============================================================

SELECT 'verify_deleted_6_33' AS step,
  (SELECT COUNT(*) FROM 5kcrm_crm_business_type WHERE type_id BETWEEN 6 AND 33) AS actual,
  0 AS expected,
  IF((SELECT COUNT(*) FROM 5kcrm_crm_business_type WHERE type_id BETWEEN 6 AND 33) = 0, 0, 1) AS FAIL_deleted_6_33;

SELECT 'verify_type_names' AS step, type_id, name, business_category
FROM 5kcrm_crm_business_type ORDER BY type_id;

SELECT 'verify_cat_column' AS step,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business_type' AND COLUMN_NAME='business_category') = 1, 0, 1) AS FAIL_cat_column;

SELECT 'verify_dup_business_category' AS step, business_category, COUNT(*) AS cnt,
  IF((SELECT COUNT(*) FROM (
      SELECT business_category FROM 5kcrm_crm_business_type
      WHERE business_category IS NOT NULL AND business_category<>''
      GROUP BY business_category HAVING COUNT(*)>1
  ) t) = 0, 0, 1) AS FAIL_dup_business_category;

SELECT 'verify_default_null' AS step, type_id, business_category,
  IF((SELECT COUNT(*) FROM 5kcrm_crm_business_type WHERE type_id=1 AND (business_category IS NOT NULL AND business_category<>'')) = 0, 0, 1) AS FAIL_default_null
FROM 5kcrm_crm_business_type WHERE type_id=1;

SELECT 'verify_uk_index' AS step,
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business_type' AND INDEX_NAME='uk_business_category') = 1, 0, 1) AS FAIL_uk_index;

SELECT 'verify_done' AS result,
  (SELECT IFNULL(SUM(cnt),0) FROM (
     SELECT IF((SELECT COUNT(*) FROM 5kcrm_crm_business_type WHERE type_id BETWEEN 6 AND 33) = 0, 0, 1) AS cnt
     UNION ALL SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business_type' AND COLUMN_NAME='business_category') = 1, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM (SELECT business_category FROM 5kcrm_crm_business_type WHERE business_category IS NOT NULL AND business_category<>'' GROUP BY business_category HAVING COUNT(*)>1) t) = 0, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM 5kcrm_crm_business_type WHERE type_id=1 AND (business_category IS NOT NULL AND business_category<>'')) = 0, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business_type' AND INDEX_NAME='uk_business_category') = 1, 0, 1)
  ) t) AS total_failures;
