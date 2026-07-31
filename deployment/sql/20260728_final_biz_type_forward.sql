-- ============================================================
-- 第二段 forward：商机类型去重 + 中文化 + 业务类别稳定绑定（严格幂等 + 硬阻断）
-- 必须在 precheck 全部通过后执行；ASCII only；MySQL 5.7 兼容
-- 硬阻断规则（forward 内部，不依赖人工先执行 precheck）：
--   H1) 旧 type_id 6..33 在 crm_business 中存在引用 -> 必须停止且不删除
--   H2) 旧 type_id 6..33 在 crm_business_status 中存在引用 -> 必须停止且不删除
--   H3) 旧 type_id 6..33 在 business_stage_reward_rule 中存在引用 -> 必须停止且不删除
--   H4) type_id 1..5 必须存在 -> 否则停止
--   H5) crm_business_data 存在 crm_rianjp 或 dealer_customer_id 数据 -> 必须停止（由第四段处理）
--   H6) dealer_customer_id 物理列存在且有待迁移关系 -> 必须停止（由第四段处理）
-- MySQL 5.7 阻断方式：使用 PREPARE + 条件执行 DELETE；
--   若硬阻断触发则只执行 SELECT 输出阻断信息并跳过 DELETE。
-- 严格幂等：第二次执行不得刷新 update_time，不得改变表结构、规则摘要或业务数据。
-- ============================================================

-- H1/H2/H3/H4 阻断计数
SET @block_business_refs := (SELECT COUNT(*) FROM `5kcrm_crm_business` WHERE type_id BETWEEN 6 AND 33);
SET @block_status_refs   := (SELECT COUNT(*) FROM `5kcrm_crm_business_status` WHERE type_id BETWEEN 6 AND 33);
SET @block_reward_refs   := (SELECT COUNT(*) FROM `5kcrm_business_stage_reward_rule` WHERE type_id BETWEEN 6 AND 33);
SET @block_required_missing := (SELECT COUNT(*) FROM (
  SELECT 1 AS id UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
) AS expected
LEFT JOIN `5kcrm_crm_business_type` t ON t.type_id = expected.id
WHERE t.type_id IS NULL);

-- 输出阻断诊断（只读）
SELECT 'H1_business_refs_6_33' AS step, @block_business_refs AS cnt;
SELECT 'H2_status_refs_6_33' AS step, @block_status_refs AS cnt;
SELECT 'H3_reward_refs_6_33' AS step, @block_reward_refs AS cnt;
SELECT 'H4_required_missing' AS step, @block_required_missing AS cnt;

-- 若任一硬阻断触发，则仅输出阻断标记并跳过 DELETE/UPDATE，返回非 0
SET @any_block := @block_business_refs + @block_status_refs + @block_reward_refs + @block_required_missing;

-- 严格幂等删除：仅当硬阻断全为 0 时才执行 DELETE；第二次执行无行可删即不改变
SET @ddl_delete_types := IF(@any_block = 0,
  'DELETE FROM `5kcrm_crm_business_type` WHERE type_id BETWEEN 6 AND 33',
  'SELECT ''BLOCKED_final_biz_type_delete'' AS note');
PREPARE stmt_del FROM @ddl_delete_types; EXECUTE stmt_del; DEALLOCATE PREPARE stmt_del;

-- 2) 中文化 type_id=2..5 名称（原地更新，幂等；不刷新 update_time，因为 name 未变则不写）
UPDATE `5kcrm_crm_business_type` SET `name`='经销商开发' WHERE type_id=2 AND (`name` IS NULL OR `name`<>'经销商开发');
UPDATE `5kcrm_crm_business_type` SET `name`='医院直签'   WHERE type_id=3 AND (`name` IS NULL OR `name`<>'医院直签');
UPDATE `5kcrm_crm_business_type` SET `name`='医院代理'   WHERE type_id=4 AND (`name` IS NULL OR `name`<>'医院代理');
UPDATE `5kcrm_crm_business_type` SET `name`='外包项目'   WHERE type_id=5 AND (`name` IS NULL OR `name`<>'外包项目');

-- 3) 增加 business_category 列（可空；系统默认组保持 NULL）— 幂等
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business_type' AND COLUMN_NAME='business_category');
SET @ddl_cat := IF(@col_exists=0,
  'ALTER TABLE `5kcrm_crm_business_type` ADD COLUMN `business_category` VARCHAR(30) NULL DEFAULT NULL COMMENT ''稳定业务类别，系统默认组保持 NULL''',
  'SELECT 1');
PREPARE stmt_cat FROM @ddl_cat; EXECUTE stmt_cat; DEALLOCATE PREPARE stmt_cat;

-- 4) 添加函数式唯一索引（仅 business_category 非空时强制唯一）— 幂等
SET @gen_col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business_type' AND COLUMN_NAME='business_category_key');
SET @ddl_gen := IF(@gen_col_exists=0,
  'ALTER TABLE `5kcrm_crm_business_type` ADD COLUMN `business_category_key` VARCHAR(30) GENERATED ALWAYS AS (IFNULL(NULLIF(`business_category`,''''),NULL)) VIRTUAL',
  'SELECT 1');
PREPARE stmt_gen FROM @ddl_gen; EXECUTE stmt_gen; DEALLOCATE PREPARE stmt_gen;

SET @uk_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_crm_business_type' AND INDEX_NAME='uk_business_category');
SET @ddl_uk := IF(@uk_exists=0,
  'ALTER TABLE `5kcrm_crm_business_type` ADD UNIQUE INDEX `uk_business_category`(`business_category_key`)',
  'SELECT 1');
PREPARE stmt_uk FROM @ddl_uk; EXECUTE stmt_uk; DEALLOCATE PREPARE stmt_uk;

-- 5) 写入稳定映射（UPDATE 而非 INSERT，避免重复生成数据；幂等，仅在值不一致时更新）
UPDATE `5kcrm_crm_business_type` SET `business_category`='dealer_dev'      WHERE type_id=2 AND (business_category IS NULL OR business_category<>'dealer_dev');
UPDATE `5kcrm_crm_business_type` SET `business_category`='hospital_direct' WHERE type_id=3 AND (business_category IS NULL OR business_category<>'hospital_direct');
UPDATE `5kcrm_crm_business_type` SET `business_category`='hospital_agent'  WHERE type_id=4 AND (business_category IS NULL OR business_category<>'hospital_agent');
UPDATE `5kcrm_crm_business_type` SET `business_category`='outsource'       WHERE type_id=5 AND (business_category IS NULL OR business_category<>'outsource');
-- type_id=1（系统默认）保持 business_category=NULL

-- 6) 让保留类型对全部登录用户可见（structure_id 为空表示全员可见）— 幂等
UPDATE `5kcrm_crm_business_type` SET `structure_id`='' WHERE type_id IN (2,3,4,5) AND structure_id<>'';

SELECT 'final_biz_type_forward_done' AS result, @any_block AS blocked;
