-- =====================================================================
-- 20260729_task_workflow_backfill_precheck.sql
-- 预检查：统计活跃项目主任务中缺失 task_workflow 的数量
-- =====================================================================

-- 活跃项目主任务（未归档、未删除、pid=0）
SELECT
  COUNT(*) AS active_main_tasks
FROM `5kcrm_task` t
WHERE t.pid = 0
  AND t.ishidden = 0
  AND t.is_archive = 0;

-- 已有 task_workflow 的数量
SELECT
  COUNT(*) AS existing_workflows
FROM `5kcrm_task_workflow` tw
INNER JOIN `5kcrm_task` t ON tw.task_id = t.task_id
WHERE t.pid = 0
  AND t.ishidden = 0
  AND t.is_archive = 0;

-- 缺失 task_workflow 的活跃主任务数量
SELECT
  COUNT(*) AS missing_workflows
FROM `5kcrm_task` t
LEFT JOIN `5kcrm_task_workflow` tw ON t.task_id = tw.task_id
WHERE t.pid = 0
  AND t.ishidden = 0
  AND t.is_archive = 0
  AND tw.task_id IS NULL;
