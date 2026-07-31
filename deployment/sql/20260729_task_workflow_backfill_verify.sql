-- =====================================================================
-- 20260729_task_workflow_backfill_verify.sql
-- 验证：输出活跃主任务数量、已有 workflow 数量、缺失数量
-- =====================================================================

-- 活跃项目主任务数量
SELECT
  COUNT(*) AS active_main_tasks
FROM `5kcrm_task` t
WHERE t.pid = 0
  AND t.ishidden = 0
  AND t.is_archive = 0;

-- 已有 workflow 数量
SELECT
  COUNT(*) AS existing_workflows
FROM `5kcrm_task_workflow` tw
INNER JOIN `5kcrm_task` t ON tw.task_id = t.task_id
WHERE t.pid = 0
  AND t.ishidden = 0
  AND t.is_archive = 0;

-- 补建后缺失数量（应为 0）
SELECT
  IF(COUNT(*) = 0, 'PASS_no_missing', CONCAT('FAIL_still_missing: ', COUNT(*))) AS verify_missing
FROM `5kcrm_task` t
LEFT JOIN `5kcrm_task_workflow` tw ON t.task_id = tw.task_id
WHERE t.pid = 0
  AND t.ishidden = 0
  AND t.is_archive = 0
  AND tw.task_id IS NULL;

-- 验证已完成任务未被重置为待评估
SELECT
  IF(COUNT(*) = 0, 'PASS_no_completed_reset', CONCAT('FAIL_completed_reset: ', COUNT(*))) AS verify_no_reset
FROM `5kcrm_task_workflow` tw
INNER JOIN `5kcrm_task` t ON tw.task_id = t.task_id
WHERE t.status = 5 AND tw.main_status = '待评估';

SELECT 'verify complete' AS result;
