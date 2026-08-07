-- 合作企业线索最小化改造：执行前只读检查。

SELECT COLUMN_NAME
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = '5kcrm_crm_customer'
   AND COLUMN_NAME IN (
     'cooperation_type','cooperation_stage','discover_user_id','verify_user_id',
     'verify_time','verify_result','verify_note'
   );

SELECT field_id, field, name, form_type
  FROM 5kcrm_admin_field
 WHERE types = 'crm_customer'
   AND field IN (
     'cooperation_type','cooperation_stage','discover_user_id','verify_user_id',
     'verify_time','verify_result','verify_note'
   );

-- 客户名称字段已有唯一性校验；这里列出历史重名企业供人工确认，不自动删除。
SELECT name, COUNT(*) AS duplicate_count, GROUP_CONCAT(customer_id ORDER BY customer_id) AS customer_ids
  FROM 5kcrm_crm_customer
 WHERE name IS NOT NULL AND TRIM(name) <> ''
 GROUP BY name
HAVING COUNT(*) > 1;

-- 应返回 0 行；若有结果，先人工合并事实，再把来源唯一键收口为两列。
SELECT source_type, source_id, COUNT(*) AS duplicate_count,
       GROUP_CONCAT(CONCAT(fact_id, ':', period, ':', status) ORDER BY fact_id) AS facts_to_review
  FROM 5kcrm_performance_fact
 GROUP BY source_type, source_id
HAVING COUNT(*) > 1;
