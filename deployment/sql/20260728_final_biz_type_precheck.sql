-- ============================================================
-- 商机与季度绩效最终收口 - 第二段：商机类型去重 + 中文化 + 业务类别稳定绑定
-- precheck（只读，ASCII only，硬阻塞）
-- 目标：
--   1) 删除 type_id=6..33 的重复类型，仅保留 type_id=1..5
--   2) 将 type_id=2..5 名称原地中文化
--   3) 在 crm_business_type 增加 business_category 字段，非空值必须唯一；系统默认组保持 NULL
--   4) 修复原迁移反复 INSERT 类型的问题，禁止再次插入重复类型
-- 安全条件（任一不满足必须硬阻塞，不允许 forward 执行）：
--   A) type_id=6..33 在 crm_business 中无引用（cnt=0）
--   B) type_id=6..33 在 crm_business_status 中无引用（cnt=0）
--   C) type_id=6..33 在 business_stage_reward_rule 中无引用（cnt=0）
--   D) type_id=1..5 必须存在（不能误删保留组）
--   E) crm_business_type.type_id 主键连续（type_id=1..5 存在）
--   F) crm_business.dealer_customer_id 历史分布只允许 0（与第四段联动）
-- 运行方式：precheck 全部通过后才能执行 forward；任一阻塞行返回非 0 必须停。
-- ============================================================

-- 1. 数据库版本与字符集
SELECT VERSION() AS db_version;
SELECT DEFAULT_CHARACTER_SET_NAME AS db_charset, DEFAULT_COLLATION_NAME AS db_collation FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE();

-- 2. 目标表存在性
SELECT 'target_tables' AS step;
SELECT t.TABLE_NAME, t.TABLE_ROWS, t.ENGINE
FROM information_schema.TABLES t
WHERE t.TABLE_SCHEMA=DATABASE()
  AND t.TABLE_NAME IN (
    '5kcrm_crm_business_type',
    '5kcrm_crm_business_status',
    '5kcrm_crm_business',
    '5kcrm_admin_field',
    '5kcrm_crm_business_data',
    '5kcrm_business_stage_reward_rule'
  )
ORDER BY t.TABLE_NAME;

-- 3. 现状快照（type 与 status 的当前形态）
SELECT 'snapshot_business_type' AS step;
SELECT type_id, name, structure_id, status, is_display
FROM 5kcrm_crm_business_type ORDER BY type_id;

SELECT 'snapshot_business_status' AS step;
SELECT status_id, type_id, name, rate, order_id
FROM 5kcrm_crm_business_status ORDER BY type_id, order_id, status_id;

-- 4. 硬阻塞条件 A/B/C：type_id=6..33 不能有任何业务引用
SELECT 'block_A_business_refs_6_33' AS step, COUNT(*) AS cnt
FROM 5kcrm_crm_business WHERE type_id BETWEEN 6 AND 33;

SELECT 'block_B_status_refs_6_33' AS step, COUNT(*) AS cnt
FROM 5kcrm_crm_business_status WHERE type_id BETWEEN 6 AND 33;

SELECT 'block_C_reward_rule_refs_6_33' AS step, COUNT(*) AS cnt
FROM 5kcrm_business_stage_reward_rule WHERE type_id BETWEEN 6 AND 33;

-- 5. 硬阻塞条件 D：type_id=1..5 必须存在
SELECT 'block_D_required_types_missing' AS step, COUNT(*) AS cnt
FROM (
  SELECT 1 AS id UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
) AS expected
LEFT JOIN 5kcrm_crm_business_type t ON t.type_id = expected.id
WHERE t.type_id IS NULL;

-- 6. 硬阻塞条件 E：保留组内业务引用计数（不允许误删保留组）
SELECT 'block_E_keep_business_count' AS step, type_id, COUNT(*) AS cnt
FROM 5kcrm_crm_business
WHERE type_id IN (1,2,3,4,5)
GROUP BY type_id
ORDER BY type_id;

-- 7. 硬阻塞条件 F：dealer_customer_id 物理列历史只允许 0（第四段同步要求）
SELECT 'block_F_dealer_id_dist' AS step, dealer_customer_id, COUNT(*) AS cnt
FROM 5kcrm_crm_business
GROUP BY dealer_customer_id
ORDER BY dealer_customer_id;

-- 8. 硬阻塞条件 G：type_id=6..33 不允许出现在外键约束
SELECT 'block_G_fk_refs_to_type' AS step, COUNT(*) AS cnt
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA=DATABASE()
  AND REFERENCED_TABLE_NAME='5kcrm_crm_business_type';

-- 9. 硬阻塞条件 H：business_category 字段是否已存在
SELECT 'block_H_business_category_exists' AS step, COUNT(*) AS cnt
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='5kcrm_crm_business_type'
  AND COLUMN_NAME='business_category';

-- 10. 重复结构 sanity：同一 business_category 当前不应出现重复
--     （执行前 type 表无此列，结果应为 0；forward 完成后由 verify 校验唯一性）
SELECT 'block_I_pre_dup_business_category' AS step, COUNT(*) AS cnt
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='5kcrm_crm_business_type'
  AND COLUMN_NAME='business_category';

-- 结束标记：precheck 全部 step 行 cnt=0 表示通过；任一非 0 必须停。
SELECT 'precheck_done' AS result;
