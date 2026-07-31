-- ============================================================
-- 第三段：商机阶段全部中文化 precheck（只读，ASCII）
-- 目标：保留 status_id，原地更新 name 为中文，避免破坏引用
-- 安全条件：
--   A) 目标 status_id 必须存在
--   B) 系统终态 status_id=1,2,3 必须保持中文（赢单/输单/无效）
--   C) type_id=1 的旧系统状态不调整（仍使用旧名称，避免影响 41 条系统默认商机）
-- ============================================================

SELECT 'precheck_target_status_ids' AS step;
SELECT status_id, type_id, name, rate, order_id
FROM 5kcrm_crm_business_status
WHERE status_id IN (1,2,3,10,11,12,13,20,21,22,23,30,31,32,33,40,41,42,43)
ORDER BY type_id, order_id, status_id;

SELECT 'precheck_done' AS result;
