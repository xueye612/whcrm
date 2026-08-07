-- 回滚说明（高风险，必须先备份并确认没有新增数据依赖）。
-- 代码应先回滚，再按需执行以下语句；默认不自动删除客户资料或绩效事实。

-- DELETE FROM 5kcrm_admin_field
--  WHERE types='crm_customer'
--    AND field IN ('cooperation_type','cooperation_stage','discover_user_id','verify_user_id','verify_time','verify_result','verify_note');

-- ALTER TABLE 5kcrm_crm_customer
--   DROP COLUMN cooperation_type,
--   DROP COLUMN cooperation_stage,
--   DROP COLUMN discover_user_id,
--   DROP COLUMN verify_user_id,
--   DROP COLUMN verify_time,
--   DROP COLUMN verify_result,
--   DROP COLUMN verify_note;

-- 已生成的 performance_fact 属于审计事实，建议保留并标记“已驳回”，不要物理删除。
