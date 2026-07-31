-- ============================================================
-- 第六段：商机阶段奖励规则 seed precheck（只读，ASCII）
-- 目标：补齐正式规则，使用 type_id + status_id 配置
-- 安全条件：
--   A) 表已存在
--   B) 当前 0 行（确认尚未补齐）
--   C) 唯一索引 uk_type_status 存在（防止重复写入）
-- ============================================================

SELECT 'precheck_rule_table' AS step, COUNT(*) AS cnt
FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_business_stage_reward_rule';

SELECT 'precheck_uk_index' AS step, COUNT(*) AS cnt
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_business_stage_reward_rule' AND INDEX_NAME='uk_type_status';

SELECT 'precheck_existing_rows' AS step, COUNT(*) AS cnt FROM 5kcrm_business_stage_reward_rule;

SELECT 'precheck_done' AS result;
