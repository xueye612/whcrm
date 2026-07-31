-- ============================================================
-- 第四段：删除重复自定义字段 precheck（只读，ASCII）
-- 目标字段：field_id=952 (crm_rianjp / 所属代理)、field_id=962 (dealer_customer_id / Dealer Customer)
-- 安全条件（任一不满足硬阻塞）：
--   A) admin_field 中两行匹配 field_id 与 field
--   B) crm_business_data 中两 field 均无历史数据（cnt=0）
--   C) crm_business.dealer_customer_id 物理列历史全部为 0
--   D) 不删除 dealer_customer_id 物理列（仅删 admin_field 定义与残留扩展数据）
-- ============================================================

-- A) admin_field 精确匹配
SELECT 'precheck_admin_field_952' AS step, field_id, field, name, types
FROM 5kcrm_admin_field WHERE field_id=952;
SELECT 'precheck_admin_field_962' AS step, field_id, field, name, types
FROM 5kcrm_admin_field WHERE field_id=962;

-- 多重匹配检查（field 字段不应重复）
SELECT 'precheck_field_field_dup' AS step, field, COUNT(*) AS cnt
FROM 5kcrm_admin_field
WHERE field IN ('crm_rianjp','dealer_customer_id')
GROUP BY field
HAVING COUNT(*)>1;

-- B) crm_business_data 中两 field 残留数据
SELECT 'precheck_biz_data_crm_rianjp' AS step, COUNT(*) AS cnt
FROM 5kcrm_crm_business_data WHERE field='crm_rianjp';
SELECT 'precheck_biz_data_dealer_customer_id' AS step, COUNT(*) AS cnt
FROM 5kcrm_crm_business_data WHERE field='dealer_customer_id';

-- C) dealer_customer_id 物理列历史分布
SELECT 'precheck_dealer_id_dist' AS step, dealer_customer_id, COUNT(*) AS cnt
FROM 5kcrm_crm_business GROUP BY dealer_customer_id ORDER BY dealer_customer_id;

SELECT 'precheck_done' AS result;
