-- 奖惩候选 occurred_time 回填：修复历史记录按日期筛选不可见问题
-- 背景：occurred_time 列在 20260727_crm_arch 迁移中后期加入（DEFAULT 0，未回填），
--       历史行 occurred_time=0，选择日期范围后被过滤掉，且“所属日期”显示为空。
-- 方案：用 create_time 回填（候选创建时刻即业务发生时刻的最佳兜底）。幂等，可重复执行。
UPDATE `5kcrm_reward_candidate`
SET `occurred_time` = `create_time`
WHERE `occurred_time` = 0 AND `create_time` > 0;

SELECT '20260902_reward_occurred_time_backfill completed' AS result;
