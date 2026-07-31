-- =====================================================================
-- 20260729_biz_status_group_reorg_forward.sql
-- 幂等迁移：商机状态组重组
-- 使用高 ID（type_id=100/101, status_id=1000-1007）避免与旧迁移冲突
-- 1. 创建直签(type_id=100)和代理签约(type_id=101)状态组
-- 2. 为两个新组创建相同的推进阶段
-- 3. 将旧组(type_id=2-5)隐藏
-- 4. 将现有商机迁移到新组（按 order_id 映射阶段）
-- 5. 从现有有效规则复制奖励规则到新组
-- 6. 将新组 type_id 写入 crm_config 供业务代码查询
-- =====================================================================

-- ========== 0. 阻断检查：type_id=100/101 或 status_id=1000-1007 已被占用时停止 ==========

SET @type100_occupied := (SELECT COUNT(*) FROM `5kcrm_crm_business_type` WHERE type_id = 100 AND business_category <> 'direct');
SET @type101_occupied := (SELECT COUNT(*) FROM `5kcrm_crm_business_type` WHERE type_id = 101 AND business_category <> 'agent');
SET @status_occupied := (SELECT COUNT(*) FROM `5kcrm_crm_business_status` WHERE status_id BETWEEN 1000 AND 1007 AND type_id NOT IN (100, 101));
SET @any_block := @type100_occupied + @type101_occupied + @status_occupied;

SELECT 'block_check' AS step, @type100_occupied AS t100, @type101_occupied AS t101, @status_occupied AS s_occupied, @any_block AS blocked;

-- ========== 1. 创建直签和代理签约状态组 ==========

-- 直签组 (type_id=100, business_category='direct')
SET @sql := IF(@any_block = 0,
  'INSERT INTO `5kcrm_crm_business_type` (type_id, name, structure_id, status, is_display, business_category) VALUES (100, ''直签'', '''', 1, 1, ''direct'') ON DUPLICATE KEY UPDATE name=''直签'', business_category=''direct'', is_display=1, status=1',
  'SELECT ''BLOCKED_type_conflict'' AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 代理签约组 (type_id=101, business_category='agent')
SET @sql := IF(@any_block = 0,
  'INSERT INTO `5kcrm_crm_business_type` (type_id, name, structure_id, status, is_display, business_category) VALUES (101, ''代理签约'', '''', 1, 1, ''agent'') ON DUPLICATE KEY UPDATE name=''代理签约'', business_category=''agent'', is_display=1, status=1',
  'SELECT ''BLOCKED_type_conflict'' AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========== 2. 为新组创建推进阶段 ==========

-- 直签组阶段 (type_id=100, status_id 1000-1003)
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1000, 100, '基础核实', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id=1000);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1001, 100, '有效联系', 0, 2
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id=1001);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1002, 100, '正式交流', 0, 3
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id=1002);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1003, 100, '明确项目', 0, 4
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id=1003);

-- 代理签约组阶段 (type_id=101, status_id 1004-1007)
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1004, 101, '基础核实', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id=1004);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1005, 101, '有效联系', 0, 2
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id=1005);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1006, 101, '正式交流', 0, 3
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id=1006);
INSERT INTO `5kcrm_crm_business_status` (status_id, type_id, name, rate, order_id)
SELECT 1007, 101, '明确项目', 0, 4
WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_crm_business_status` WHERE status_id=1007);

-- ========== 3. 隐藏旧组并清空 business_category ==========

UPDATE `5kcrm_crm_business_type` SET business_category=NULL, is_display=0
WHERE type_id IN (2,3,4,5) AND (business_category IS NOT NULL OR is_display<>0);

-- ========== 4. 迁移现有商机到新组 ==========

-- 4a. 有代理商的商机 -> 代理签约组 (type_id=101)
UPDATE `5kcrm_crm_business` b
LEFT JOIN `5kcrm_crm_business_status` old_s ON b.status_id = old_s.status_id
LEFT JOIN `5kcrm_crm_business_status` new_s ON new_s.type_id = 101 AND new_s.order_id = old_s.order_id
SET b.type_id = 101,
    b.status_id = CASE
      WHEN b.is_end != 0 THEN b.status_id
      WHEN new_s.status_id IS NOT NULL THEN new_s.status_id
      ELSE 1004
    END
WHERE b.dealer_customer_id > 0
  AND b.type_id IN (1,2,3,4,5)
  AND b.is_deleted = 0;

-- 4b. 无代理商的商机 -> 直签组 (type_id=100)
UPDATE `5kcrm_crm_business` b
LEFT JOIN `5kcrm_crm_business_status` old_s ON b.status_id = old_s.status_id
LEFT JOIN `5kcrm_crm_business_status` new_s ON new_s.type_id = 100 AND new_s.order_id = old_s.order_id
SET b.type_id = 100,
    b.status_id = CASE
      WHEN b.is_end != 0 THEN b.status_id
      WHEN new_s.status_id IS NOT NULL THEN new_s.status_id
      ELSE 1000
    END
WHERE (b.dealer_customer_id = 0 OR b.dealer_customer_id IS NULL)
  AND b.type_id IN (1,2,3,4,5)
  AND b.is_deleted = 0;

-- ========== 5. 从现有有效规则复制奖励规则到新组 ==========

-- 直签组：从旧组规则按 order_id 复制
-- 取每个 order_id 的第一条有效规则作为来源
INSERT INTO `5kcrm_business_stage_reward_rule`
  (rule_name, direction, source_type, type_id, status_id, calc_method, amount,
   single_cap, monthly_cap, need_review, auto_generate, effective_date, expiry_date,
   description, rules_version, is_enabled, create_time, update_time)
SELECT
  CONCAT('直签-', new_s.name) AS rule_name,
  src.direction, src.source_type, 100 AS type_id, new_s.status_id,
  src.calc_method, src.amount, src.single_cap, src.monthly_cap,
  src.need_review, src.auto_generate, src.effective_date, src.expiry_date,
  src.description, src.rules_version, src.is_enabled,
  UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `5kcrm_crm_business_status` new_s
-- 对每个新组阶段，找到旧组中相同 order_id 的第一条有效规则
LEFT JOIN (
  SELECT s.order_id, r.* FROM `5kcrm_business_stage_reward_rule` r
  JOIN `5kcrm_crm_business_status` s ON r.status_id = s.status_id
  JOIN `5kcrm_crm_business_type` t ON r.type_id = t.type_id
  WHERE r.is_enabled = 1 AND t.is_display = 0
  AND r.rule_id = (SELECT MIN(r2.rule_id) FROM `5kcrm_business_stage_reward_rule` r2
                    JOIN `5kcrm_crm_business_status` s2 ON r2.status_id = s2.status_id
                    WHERE r2.is_enabled = 1 AND s2.order_id = s.order_id)
) src ON src.order_id = new_s.order_id
WHERE new_s.type_id = 100
  AND src.rule_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `5kcrm_business_stage_reward_rule` existing
    WHERE existing.type_id = 100 AND existing.status_id = new_s.status_id
  );

-- 代理签约组：同样复制
INSERT INTO `5kcrm_business_stage_reward_rule`
  (rule_name, direction, source_type, type_id, status_id, calc_method, amount,
   single_cap, monthly_cap, need_review, auto_generate, effective_date, expiry_date,
   description, rules_version, is_enabled, create_time, update_time)
SELECT
  CONCAT('代理签约-', new_s.name) AS rule_name,
  src.direction, src.source_type, 101 AS type_id, new_s.status_id,
  src.calc_method, src.amount, src.single_cap, src.monthly_cap,
  src.need_review, src.auto_generate, src.effective_date, src.expiry_date,
  src.description, src.rules_version, src.is_enabled,
  UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `5kcrm_crm_business_status` new_s
LEFT JOIN (
  SELECT s.order_id, r.* FROM `5kcrm_business_stage_reward_rule` r
  JOIN `5kcrm_crm_business_status` s ON r.status_id = s.status_id
  JOIN `5kcrm_crm_business_type` t ON r.type_id = t.type_id
  WHERE r.is_enabled = 1 AND t.is_display = 0
  AND r.rule_id = (SELECT MIN(r2.rule_id) FROM `5kcrm_business_stage_reward_rule` r2
                    JOIN `5kcrm_crm_business_status` s2 ON r2.status_id = s2.status_id
                    WHERE r2.is_enabled = 1 AND s2.order_id = s.order_id)
) src ON src.order_id = new_s.order_id
WHERE new_s.type_id = 101
  AND src.rule_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `5kcrm_business_stage_reward_rule` existing
    WHERE existing.type_id = 101 AND existing.status_id = new_s.status_id
  );

-- ========== 6. 将新组 type_id 写入 crm_config ==========

INSERT INTO `5kcrm_crm_config` (`name`, `value`, `description`)
VALUES ('business_type_id_direct', '100', '直签状态组type_id')
ON DUPLICATE KEY UPDATE `value` = '100';

INSERT INTO `5kcrm_crm_config` (`name`, `value`, `description`)
VALUES ('business_type_id_agent', '101', '代理签约状态组type_id')
ON DUPLICATE KEY UPDATE `value` = '101';

SELECT '20260729_biz_status_group_reorg_forward completed' AS result, @any_block AS was_blocked;
