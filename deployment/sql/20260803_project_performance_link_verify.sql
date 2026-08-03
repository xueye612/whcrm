-- ======================================================================
-- VERIFY：迁移后校验列与索引已创建。
-- ======================================================================

-- 1) work_milestone 新列与索引
SELECT COLUMN_NAME FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_milestone'
  AND COLUMN_NAME='responsible_user_id';
SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_in_index
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_milestone'
  AND INDEX_NAME IN ('idx_responsible_user_id','uk_milestone_exact')
  GROUP BY INDEX_NAME, NON_UNIQUE;

-- 2) work_member_contribution 新列与索引
SELECT COLUMN_NAME FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_member_contribution'
  AND COLUMN_NAME IN ('status','confirm_user_id','confirm_time');
SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_in_index
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_work_member_contribution'
  AND INDEX_NAME IN ('idx_contribution_status','uk_contribution_exact')
  GROUP BY INDEX_NAME, NON_UNIQUE;

-- 3) performance_fact 来源唯一键
SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_in_index
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance_fact'
  AND INDEX_NAME='uk_fact_source'
  GROUP BY INDEX_NAME, NON_UNIQUE;

SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_in_index
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_performance'
  AND INDEX_NAME='uk_perf_user_period'
  GROUP BY INDEX_NAME, NON_UNIQUE;

-- 4) 确认迁移后无新增重复
SELECT source_type, source_id, COUNT(*) AS dup_cnt
  FROM 5kcrm_performance_fact
  GROUP BY source_type, source_id
  HAVING COUNT(*) > 1;

SELECT user_id, period, COUNT(*) AS dup_cnt
  FROM 5kcrm_performance
  GROUP BY user_id, period
  HAVING COUNT(*) > 1;

SELECT TABLE_NAME FROM information_schema.TABLES
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_project_performance_audit';
