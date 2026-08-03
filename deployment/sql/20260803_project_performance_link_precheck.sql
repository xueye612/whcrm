-- ======================================================================
-- PRECHECK：建唯一键前必须确认历史重复数据，不得引用尚未创建的列。
-- 仅查询，不修改。若返回非 0 行，需先人工合并/标注，再执行 forward。
-- ======================================================================

-- 1) 旧里程碑重复（仅使用迁移前已存在的列：work_id + milestone_type + name + plan_time）
SELECT work_id, milestone_type, name, plan_time, COUNT(*) AS dup_cnt,
       GROUP_CONCAT(milestone_id ORDER BY milestone_id) AS milestone_ids_to_review
  FROM 5kcrm_work_milestone
  GROUP BY work_id, milestone_type, name, plan_time
  HAVING COUNT(*) > 1;

-- 2) 贡献精确重复（work_id + user_id + contribution_role + start_time + end_time）
SELECT work_id, user_id, contribution_role, start_time, end_time, COUNT(*) AS dup_cnt,
       GROUP_CONCAT(contribution_id ORDER BY contribution_id) AS contribution_ids_to_review
  FROM 5kcrm_work_member_contribution
  GROUP BY work_id, user_id, contribution_role, start_time, end_time
  HAVING COUNT(*) > 1;

-- 3) performance_fact 同 source_type+source_id 存在多条
SELECT source_type, source_id, COUNT(*) AS dup_cnt,
       GROUP_CONCAT(CONCAT(fact_id, ':', period, ':', status) ORDER BY fact_id) AS facts_to_review
  FROM 5kcrm_performance_fact
  GROUP BY source_type, source_id
  HAVING COUNT(*) > 1;

-- 4) performance_fact 跨季度多条（同 source_type+source_id 但不同 period）
SELECT source_type, source_id, COUNT(DISTINCT period) AS period_cnt,
       GROUP_CONCAT(CONCAT(fact_id, ':', period, ':', status) ORDER BY fact_id) AS cross_period_facts_to_review
  FROM 5kcrm_performance_fact
  GROUP BY source_type, source_id
  HAVING COUNT(DISTINCT period) > 1;

-- 5) performance 同一员工、季度存在多条汇总（ensureSummary 并发保护前置）
SELECT user_id, period, COUNT(*) AS dup_cnt,
       GROUP_CONCAT(perf_id ORDER BY perf_id) AS perf_ids_to_review
  FROM 5kcrm_performance
  GROUP BY user_id, period
  HAVING COUNT(*) > 1;

-- 人工处理清单：对上述查询返回的每条重复记录，需人工决定保留哪一条，
-- 其余标注作废或合并。不得静默删除。处理完毕后再执行 forward 迁移。
