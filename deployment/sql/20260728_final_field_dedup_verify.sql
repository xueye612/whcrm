-- ============================================================
-- 第四段 verify：确认两条 admin_field 已删除、扩展数据无残留
-- ============================================================

SELECT 'verify_field_952_gone' AS step, COUNT(*) AS actual, 0 AS expected,
  IF((SELECT COUNT(*) FROM 5kcrm_admin_field WHERE field_id=952 OR field='crm_rianjp') = 0, 0, 1) AS FAIL_field_952_gone;

SELECT 'verify_field_962_gone' AS step, COUNT(*) AS actual, 0 AS expected,
  IF((SELECT COUNT(*) FROM 5kcrm_admin_field WHERE field_id=962 OR field='dealer_customer_id') = 0, 0, 1) AS FAIL_field_962_gone;

SELECT 'verify_biz_data_residue' AS step, field, COUNT(*) AS cnt,
  IF((SELECT COUNT(*) FROM 5kcrm_crm_business_data WHERE field IN ('crm_rianjp','dealer_customer_id')) = 0, 0, 1) AS FAIL_biz_data_residue
FROM 5kcrm_crm_business_data
WHERE field IN ('crm_rianjp','dealer_customer_id')
GROUP BY field;

-- dealer_customer_id 物理列仍保留
SELECT 'verify_dealer_col_still' AS step,
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business' AND COLUMN_NAME='dealer_customer_id') = 1, 0, 1) AS FAIL_dealer_col_still;

SELECT 'verify_done' AS result,
  (SELECT IFNULL(SUM(cnt),0) FROM (
     SELECT IF((SELECT COUNT(*) FROM 5kcrm_admin_field WHERE field_id=952 OR field='crm_rianjp') = 0, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM 5kcrm_admin_field WHERE field_id=962 OR field='dealer_customer_id') = 0, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM 5kcrm_crm_business_data WHERE field IN ('crm_rianjp','dealer_customer_id')) = 0, 0, 1)
     UNION ALL SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business' AND COLUMN_NAME='dealer_customer_id') = 1, 0, 1)
  ) t) AS total_failures;
