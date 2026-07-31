-- =====================================================================
-- 20260729_task_workflow_backfill_forward.sql
-- 幂等迁移：为活跃项目主任务补建 task_workflow
-- 
-- 状态映射规则：
--   status=1（未开始）-> 待评估
--   status in (2,3)（进行中）-> 处理中
--   status=5（已完成）-> 已完成（只读，不会被重置为待评估）
--   status=4 或 is_archive=1 -> 不补建
-- =====================================================================

-- 为未开始任务补建 workflow（status=1 -> 待评估）
INSERT IGNORE INTO `5kcrm_task_workflow` (task_id, main_status, aux_status, workflow_version, version, create_time, update_time)
SELECT
  t.task_id,
  '待评估',
  '',
  1,
  1,
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP()
FROM `5kcrm_task` t
LEFT JOIN `5kcrm_task_workflow` tw ON t.task_id = tw.task_id
WHERE t.pid = 0
  AND t.ishidden = 0
  AND t.is_archive = 0
  AND t.status = 1
  AND tw.task_id IS NULL;

-- 为进行中任务补建 workflow（status in (2,3) -> 处理中）
INSERT IGNORE INTO `5kcrm_task_workflow` (task_id, main_status, aux_status, workflow_version, version, create_time, update_time)
SELECT
  t.task_id,
  '处理中',
  '',
  1,
  1,
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP()
FROM `5kcrm_task` t
LEFT JOIN `5kcrm_task_workflow` tw ON t.task_id = tw.task_id
WHERE t.pid = 0
  AND t.ishidden = 0
  AND t.is_archive = 0
  AND t.status IN (2, 3)
  AND tw.task_id IS NULL;

-- 为已完成任务补建 workflow（status=5 -> 已完成，只读）
INSERT IGNORE INTO `5kcrm_task_workflow` (task_id, main_status, aux_status, workflow_version, version, create_time, update_time)
SELECT
  t.task_id,
  '已完成',
  '',
  1,
  1,
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP()
FROM `5kcrm_task` t
LEFT JOIN `5kcrm_task_workflow` tw ON t.task_id = tw.task_id
WHERE t.pid = 0
  AND t.ishidden = 0
  AND t.is_archive = 0
  AND t.status = 5
  AND tw.task_id IS NULL;

SELECT '20260729_task_workflow_backfill_forward completed' AS result;
