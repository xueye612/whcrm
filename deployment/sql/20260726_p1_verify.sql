-- =============================================================================
-- P1 迁移后只读核验（不写入数据）
-- =============================================================================

-- 1. 新建 4 张表存在且为空结构（迁移不写入业务数据）
SELECT TABLE_NAME, TABLE_ROWS, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_work_profile','5kcrm_work_milestone','5kcrm_work_member_contribution','5kcrm_work_knowledge_link');

-- 2. work 表未被改写（仍无实施字段；历史项目数量不变）
SELECT
  COUNT(*) AS total_works,
  SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_works
FROM 5kcrm_work;

-- 3. 里程碑/贡献/链接表的关键索引存在
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('5kcrm_work_milestone','5kcrm_work_member_contribution','5kcrm_work_knowledge_link')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 4. 幂等性说明：重复运行前向迁移应全部 CREATE TABLE IF NOT EXISTS 跳过，无报错。

-- 5. 孤儿档案核验（应为 0；迁移不写入数据）
SELECT COUNT(*) AS orphan_profiles
FROM 5kcrm_work_profile p
LEFT JOIN 5kcrm_work w ON w.work_id = p.work_id
WHERE w.work_id IS NULL;
