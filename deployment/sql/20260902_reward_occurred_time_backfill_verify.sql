-- 奖惩候选 occurred_time 回填：执行后验证
-- 期望 remaining_zero 等于 0（或仅剩 create_time 也为 0 的异常行，backfilled 大于 0 表示本次有回填）
SELECT
  (SELECT COUNT(*) FROM `5kcrm_reward_candidate` WHERE `occurred_time` = 0 AND `create_time` > 0) AS remaining_zero,
  (SELECT COUNT(*) FROM `5kcrm_reward_candidate` WHERE `occurred_time` > 0) AS dated_rows,
  (SELECT COUNT(*) FROM `5kcrm_reward_candidate`) AS total_rows;
