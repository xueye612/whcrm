-- 合作企业线索最小化改造：迁移后校验。

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = '5kcrm_crm_customer'
   AND COLUMN_NAME IN (
     'cooperation_type','cooperation_stage','discover_user_id','verify_user_id',
     'verify_time','verify_result','verify_note'
   )
 ORDER BY ORDINAL_POSITION;

SELECT field, name, form_type, is_null, setting
  FROM 5kcrm_admin_field
 WHERE types = 'crm_customer'
   AND field IN (
     'cooperation_type','cooperation_stage','discover_user_id','verify_user_id',
     'verify_time','verify_result','verify_note'
   )
 ORDER BY order_id;

SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_in_index
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = '5kcrm_performance_fact'
   AND INDEX_NAME = 'uk_fact_source'
 GROUP BY INDEX_NAME, NON_UNIQUE;

-- 应返回 0 行：同一企业核实事实不得重复。
SELECT source_type, source_id, COUNT(*) AS duplicate_count
  FROM 5kcrm_performance_fact
 WHERE source_type = 'cooperation_customer_verify'
 GROUP BY source_type, source_id
HAVING COUNT(*) > 1;
