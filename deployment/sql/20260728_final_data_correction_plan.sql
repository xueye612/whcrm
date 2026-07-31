-- ============================================================
-- 正式库数据修正脚本（只读审查 + 人工执行修正 SQL）
-- 目标：
--   1) 清理 4 条错误生成的 ledger_missing_desc 负向绩效事实
--   2) 历史商机 business_category 回填（基于客户类型推断，需人工复核）
--   3) 12 条孤儿商机状态修正（必须先 SELECT 输出，再人工决定映射，禁止直接猜测）
--
-- 重要：本脚本不自动执行任何 UPDATE/DELETE；只输出诊断信息和修正建议 SQL。
--       必须由 DBA/管理员在审核输出后人工执行建议语句。
--       正式库不得写入测试数据。
-- ============================================================

-- ============================================================
-- 1) 清理 4 条错误生成的 ledger_missing_desc 负向绩效事实
-- ============================================================

SELECT '== P0-4 错误事实诊断 ==' AS step;
SELECT fact_id, user_id, period, source_type, source_id, title, occurred_time, status
FROM 5kcrm_performance_fact
WHERE source_type = 'ledger_missing_desc';

-- 建议修正 SQL（人工执行）：
-- DELETE FROM 5kcrm_performance_fact WHERE source_type = 'ledger_missing_desc';
-- 或保留审计痕迹，仅标记为已驳回：
-- UPDATE 5kcrm_performance_fact
--   SET status='已驳回', review_note='台账质量问题需经登记/确认流程；此事实为错误生成，已驳回',
--   update_time=UNIX_TIMESTAMP()
-- WHERE source_type = 'ledger_missing_desc';

-- 推荐方式：保留审计痕迹，更新为已驳回（不删除）
SELECT '== P0-4 建议修正 SQL（人工执行）==' AS step;
SELECT CONCAT('UPDATE 5kcrm_performance_fact SET status=''已驳回'', review_note=''台账质量问题需经登记/确认流程；此事实为错误生成，已驳回'', update_time=UNIX_TIMESTAMP() WHERE source_type=''ledger_missing_desc'';') AS suggested_sql;


-- ============================================================
-- 2) 历史商机 business_category 回填（41 条空值）
--    回填规则：基于客户类型推断，但必须人工复核
--    - 客户类型为 dealer -> dealer_dev
--    - 客户类型为 hospital + signing_method=dealer_signed -> hospital_agent
--    - 客户类型为 hospital + signing_method=company_direct -> hospital_direct
--    - 其他无法推断 -> 标记为待人工确认（不自动回填）
-- ============================================================

SELECT '== 历史商机类别回填诊断 ==' AS step;
SELECT b.business_id, b.customer_id, c.customer_type, b.signing_method,
       b.type_id, b.business_category,
       CASE
         WHEN c.customer_type='dealer' THEN 'dealer_dev'
         WHEN c.customer_type='hospital' AND b.signing_method='dealer_signed' THEN 'hospital_agent'
         WHEN c.customer_type='hospital' AND b.signing_method='company_direct' THEN 'hospital_direct'
         ELSE '待人工确认'
       END AS suggested_category
FROM 5kcrm_crm_business b
LEFT JOIN 5kcrm_crm_customer c ON c.customer_id=b.customer_id
WHERE (b.business_category IS NULL OR b.business_category='')
ORDER BY b.business_id;

-- 建议修正 SQL（人工执行；仅对能明确推断的行回填）：
SELECT '== 建议修正 SQL（仅明确推断行；人工执行）==' AS step;
SELECT CONCAT(
  'UPDATE 5kcrm_crm_business SET business_category=''dealer_dev'', type_id=2 WHERE business_id=', b.business_id, ' AND (business_category IS NULL OR business_category='''');'
) AS suggested_sql
FROM 5kcrm_crm_business b
LEFT JOIN 5kcrm_crm_customer c ON c.customer_id=b.customer_id
WHERE (b.business_category IS NULL OR b.business_category='')
  AND c.customer_type='dealer'
ORDER BY b.business_id;

SELECT CONCAT(
  'UPDATE 5kcrm_crm_business SET business_category=''hospital_agent'', type_id=4 WHERE business_id=', b.business_id, ' AND (business_category IS NULL OR business_category='''');'
) AS suggested_sql
FROM 5kcrm_crm_business b
LEFT JOIN 5kcrm_crm_customer c ON c.customer_id=b.customer_id
WHERE (b.business_category IS NULL OR b.business_category='')
  AND c.customer_type='hospital'
  AND b.signing_method='dealer_signed'
ORDER BY b.business_id;

SELECT CONCAT(
  'UPDATE 5kcrm_crm_business SET business_category=''hospital_direct'', type_id=3 WHERE business_id=', b.business_id, ' AND (business_category IS NULL OR business_category='''');'
) AS suggested_sql
FROM 5kcrm_crm_business b
LEFT JOIN 5kcrm_crm_customer c ON c.customer_id=b.customer_id
WHERE (b.business_category IS NULL OR b.business_category='')
  AND c.customer_type='hospital'
  AND b.signing_method='company_direct'
ORDER BY b.business_id;

-- 无法明确推断的行（如外包项目、客户类型为空）必须人工确认，不自动回填
SELECT '== 无法自动推断的行（需人工确认）==' AS step;
SELECT b.business_id, b.customer_id, c.customer_type, b.signing_method, b.type_id
FROM 5kcrm_crm_business b
LEFT JOIN 5kcrm_crm_customer c ON c.customer_id=b.customer_id
WHERE (b.business_category IS NULL OR b.business_category='')
  AND NOT (
    c.customer_type='dealer'
    OR (c.customer_type='hospital' AND b.signing_method='dealer_signed')
    OR (c.customer_type='hospital' AND b.signing_method='company_direct')
  )
ORDER BY b.business_id;


-- ============================================================
-- 3) 12 条孤儿商机状态修正
--    不允许直接猜测映射；必须先 SELECT 输出现状，再人工决定
-- ============================================================

SELECT '== 孤儿商机状态诊断 ==' AS step;
SELECT b.business_id, b.type_id, b.status_id, b.is_end,
       bt.name AS type_name,
       bs.name AS status_name_in_table
FROM 5kcrm_crm_business b
LEFT JOIN 5kcrm_crm_business_status s ON s.type_id=b.type_id AND s.status_id=b.status_id
LEFT JOIN 5kcrm_crm_business_type bt ON bt.type_id=b.type_id
LEFT JOIN 5kcrm_crm_business_status bs ON bs.status_id=b.status_id
WHERE b.is_end=0
  AND s.status_id IS NULL
ORDER BY b.business_id;

-- 对于每一条孤儿状态，需要人工决定：
--   a) 该商机的 type_id 是否仍然有效（可能是被迁移删除的旧 type_id）
--   b) 当前 status_id 是否应映射到 type_id 对应状态组的某个 status_id
--   c) 是否应直接终态化（is_end=1/2/3）
-- 以下输出建议修正 SQL（仅作示例；实际映射必须人工审核后执行）
SELECT '== 建议修正 SQL（必须人工审核映射后执行）==' AS step;
SELECT CONCAT(
  '-- business_id=', b.business_id, ' type_id=', b.type_id, ' status_id=', b.status_id, ' 当前状态不在表中，请人工审核后执行：',
  'UPDATE 5kcrm_crm_business SET status_id=<目标status_id>, is_end=<0或1/2/3>, update_time=UNIX_TIMESTAMP() WHERE business_id=', b.business_id, ';'
) AS suggested_sql
FROM 5kcrm_crm_business b
LEFT JOIN 5kcrm_crm_business_status s ON s.type_id=b.type_id AND s.status_id=b.status_id
WHERE b.is_end=0
  AND s.status_id IS NULL
ORDER BY b.business_id;

SELECT 'data_correction_plan_done' AS result;
