-- =====================================================================
-- 20260731_biz_type_restore_diagnose.sql
-- 恢复被误停用的商机组 + 诊断查询（幂等；MySQL 5.7 兼容）
--
-- 背景：
--   "删除商机组"实际是把 is_display 设置为 0（软删除），但可能导致新建商机时
--   选不到该组，且历史奖励规则仍需按 type_id/status_id 关联显示原名称。
--   本脚本用于：确认目标组 → 恢复显示/启用 → 确认阶段仍在 → 清除应用缓存。
--
-- 重要原则：
--   1) 先查询确认原组，不要新建重复组（新建会生成新 type_id，历史数据失联）
--   2) 阶段记录若已被物理删除，必须从备份按原 status_id 恢复，不能生成新 ID
--   3) 恢复后必须清除 BI_queryCache_StatusList_Data 应用缓存（见底部说明）
-- =====================================================================

-- ========== 步骤 0：诊断 - 列出所有商机组（含已停用） ==========
-- 确认目标 type_id，不要凭名称猜测
SELECT '=== 步骤0: 全部商机组（含停用） ===' AS step;
SELECT
    t.type_id,
    t.name,
    t.status        AS type_status,
    t.is_display,
    t.business_category,
    t.structure_id,
    (SELECT COUNT(*) FROM 5kcrm_crm_business b WHERE b.type_id = t.type_id) AS business_count,
    (SELECT COUNT(*) FROM 5kcrm_business_stage_reward_rule r WHERE r.type_id = t.type_id) AS reward_rule_count
FROM 5kcrm_crm_business_type t
ORDER BY t.type_id;

-- ========== 步骤 1：诊断 - 检查目标组的阶段是否仍存在 ==========
-- 将下面的 @restore_type_id 替换为步骤0确认的原商机组ID
SET @restore_type_id := 0;  -- ← 填入确认后的原商机组ID

SELECT '=== 步骤1: 目标组的阶段列表 ===' AS step;
SELECT
    status_id,
    type_id,
    name,
    order_id,
    rate
FROM 5kcrm_crm_business_status
WHERE type_id = @restore_type_id
ORDER BY order_id;

-- ========== 步骤 2：诊断 - 检查是否有阶段被物理删除 ==========
-- 如果阶段数量少于预期，说明阶段已被物理删除，需从备份恢复（不能新建）
SELECT '=== 步骤2: 阶段完整性检查 ===' AS step;
SELECT
    @restore_type_id AS type_id,
    COUNT(*) AS stage_count,
    SUM(CASE WHEN order_id < 99 THEN 1 ELSE 0 END) AS non_terminal_count,
    MIN(order_id) AS min_order,
    MAX(order_id) AS max_order
FROM 5kcrm_crm_business_status
WHERE type_id = @restore_type_id;

-- ========== 步骤 3：恢复 - 启用并显示目标商机组 ==========
-- 仅在 @restore_type_id > 0 且阶段存在时执行
SELECT '=== 步骤3: 恢复商机组显示与启用 ===' AS step;
UPDATE 5kcrm_crm_business_type
SET is_display = 1, status = 1
WHERE type_id = @restore_type_id
  AND @restore_type_id > 0;

-- 确认恢复结果
SELECT type_id, name, status, is_display
FROM 5kcrm_crm_business_type
WHERE type_id = @restore_type_id;

-- ========== 步骤 4：验证 - 恢复后阶段与历史数据关联完整性 ==========
SELECT '=== 步骤4: 关联完整性验证 ===' AS step;
-- 阶段仍存在且关联正确
SELECT
    s.status_id,
    s.name   AS stage_name,
    s.order_id,
    COUNT(b.business_id) AS business_using_stage
FROM 5kcrm_crm_business_status s
LEFT JOIN 5kcrm_crm_business b ON b.status_id = s.status_id AND b.type_id = s.type_id
WHERE s.type_id = @restore_type_id
GROUP BY s.status_id, s.name, s.order_id
ORDER BY s.order_id;

-- 奖励规则仍能关联到正确阶段
SELECT
    r.rule_id,
    r.type_id,
    r.status_id,
    s.name AS stage_name,
    r.amount,
    r.is_enabled
FROM 5kcrm_business_stage_reward_rule r
LEFT JOIN 5kcrm_crm_business_status s ON r.status_id = s.status_id AND r.type_id = s.type_id
WHERE r.type_id = @restore_type_id
ORDER BY s.order_id;

-- ========== 步骤 5：清除应用缓存 ==========
-- 重要：BI_queryCache_StatusList_Data 是 ThinkPHP 文件/缓存驱动存储的，
-- 无法通过 SQL 直接清除。恢复后必须通过以下任一方式清除：
--   方式A（推荐）：重启 PHP-FPM / Web 进程后，访问一次 /crm/business/statusList
--                  （模型层的 create/update/del 已会自动 cache(..., NULL)）
--   方式B：手动删除 runtime/temp 目录下的缓存文件
--   方式C：执行下方清理语句（仅当使用数据库缓存驱动 runtime/temp 时无效，需在应用层执行）
SELECT '=== 步骤5: 应用缓存需在应用层清除（BI_queryCache_StatusList_Data） ===' AS note;
-- 如使用 Redis 缓存，可执行：DEL BI_queryCache_StatusList_Data
-- 如使用 Memcache，可执行对应 delete 操作
-- 如使用文件缓存，删除 runtime/temp/ 下含该 key 的文件

-- ========== 阶段被物理删除时的恢复指引（仅诊断，不自动执行） ==========
-- 如果步骤2发现阶段已被物理删除，必须从备份按原 status_id 恢复：
--
-- 1. 从备份库查出原始阶段记录：
--    SELECT status_id, type_id, name, order_id, rate
--    FROM 5kcrm_crm_business_status
--    WHERE type_id = <原商机组ID>
--    ORDER BY order_id;
--
-- 2. 按原 status_id 重新插入（必须保留原 ID，否则历史商机和奖励规则失联）：
--    INSERT INTO 5kcrm_crm_business_status (status_id, type_id, name, order_id, rate)
--    VALUES (<原status_id>, <原type_id>, '<阶段名>', <order_id>, <rate>);
--    （对每个缺失阶段逐条执行）
--
-- 注意：绝不使用 AUTO_INCREMENT 生成新 status_id，否则：
--   - 历史商机 status_id 指向不存在的阶段
--   - 历史奖励规则 status_id 无法关联到正确阶段
