-- ============================================================
-- 第三段 verify：确认阶段名称全部中文化，无英文残留
-- ============================================================

SELECT 'verify_no_english_status' AS step,
  (SELECT COUNT(*) FROM 5kcrm_crm_business_status
   WHERE name REGEXP '^(base_verify|effective_contact|formal_exchange|formal_visit|clear_project|formal_requirement|proposal_quote|signed|win|lost|invalid|status_change|business_stage)$'
  ) AS actual,
  0 AS expected,
  IF((SELECT COUNT(*) FROM 5kcrm_crm_business_status
   WHERE name REGEXP '^(base_verify|effective_contact|formal_exchange|formal_visit|clear_project|formal_requirement|proposal_quote|signed|win|lost|invalid|status_change|business_stage)$') = 0, 0, 1) AS FAIL_no_english_status;

SELECT 'verify_status_names' AS step, status_id, type_id, name
FROM 5kcrm_crm_business_status
WHERE status_id IN (10,11,12,13,20,21,22,23,30,31,32,33,40,41,42,43,1,2,3)
ORDER BY type_id, status_id;

SELECT 'verify_done' AS result,
  IF((SELECT COUNT(*) FROM 5kcrm_crm_business_status
   WHERE name REGEXP '^(base_verify|effective_contact|formal_exchange|formal_visit|clear_project|formal_requirement|proposal_quote|signed|win|lost|invalid|status_change|business_stage)$') = 0, 0, 1) AS total_failures;
