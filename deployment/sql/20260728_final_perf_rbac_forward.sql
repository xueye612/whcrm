-- ============================================================
-- 第七段 forward：绩效状态字典 + RBAC 子权限（严格幂等 + 按规则名定位）
-- ASCII only；MySQL 5.7 兼容
-- 设计要点：
--   1) 修正 performance.status / performance_fact.status / direction 默认值为中文
--   2) 历史 status 英文枚举修正为中文
--   3) 在 admin_rule 注册 perf_* 子权限规则（pid=442 季度绩效）
--   4) 不假设超级管理员组 id=1；通过 admin_rule.name=perf_* 定位规则 id，
--      通过 admin_group.rules 携带；
--      由后续 PHP/管理员操作决定实际授予哪些组，本脚本只创建规则定义。
-- 严格幂等：第二次执行不改变 rules_version、不刷新 update_time、不重复 INSERT。
-- ============================================================

-- 1) 修正默认状态为中文（幂等）
ALTER TABLE `5kcrm_performance` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT '待确认';
ALTER TABLE `5kcrm_performance_fact` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT '待审核';
ALTER TABLE `5kcrm_performance_fact` MODIFY COLUMN `direction` VARCHAR(10) NOT NULL DEFAULT '正向';

-- 2) 历史 status 英文枚举修正为中文
UPDATE `5kcrm_performance` SET `status`='待确认' WHERE `status`='pending_confirmation';
UPDATE `5kcrm_performance` SET `status`='已确认' WHERE `status`='approved';
UPDATE `5kcrm_performance` SET `status`='已退回' WHERE `status`='returned';
UPDATE `5kcrm_performance_fact` SET `status`='待审核' WHERE `status`='pending_review';
UPDATE `5kcrm_performance_fact` SET `status`='已通过' WHERE `status`='approved';
UPDATE `5kcrm_performance_fact` SET `status`='已驳回' WHERE `status`='rejected';
UPDATE `5kcrm_performance_fact` SET `direction`='正向' WHERE `direction`='positive';
UPDATE `5kcrm_performance_fact` SET `direction`='负向' WHERE `direction`='negative';

-- 3) 注册 RBAC 子权限规则（pid=442 季度绩效；幂等；不依赖固定 admin_rule id）
--    通过 name+pid 复合条件判断是否已存在，不重复 INSERT
INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '查看本人绩效', 'perf_view_self', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_view_self' AND `pid`=442);

INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '查看下属绩效', 'perf_view_subordinates', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_view_subordinates' AND `pid`=442);

INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '自动归集', 'perf_auto_aggregate', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_auto_aggregate' AND `pid`=442);

INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '补录绩效事实', 'perf_fact_input', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_fact_input' AND `pid`=442);

INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '审核绩效事实', 'perf_fact_review', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_fact_review' AND `pid`=442);

INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '录入维度得分', 'perf_score_input', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_score_input' AND `pid`=442);

INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '最终评级', 'perf_final_rate', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_final_rate' AND `pid`=442);

INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '责任认定', 'perf_responsibility', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_responsibility' AND `pid`=442);

-- 4) 责任认定审核子权限（便于有审核权限且不是被考核人/提交人的审核人审核）
INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '责任认定审核', 'perf_responsibility_review', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_responsibility_review' AND `pid`=442);

-- 5) 台账质量问题审核子权限
INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '台账质量问题审核', 'perf_ledger_quality_review', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_ledger_quality_review' AND `pid`=442);

-- 6) 绩效重新提交子权限
INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2, '绩效重新提交', 'perf_summary_recommit', 3, 442, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `5kcrm_admin_rule` WHERE `name`='perf_summary_recommit' AND `pid`=442);

-- 7) 不假设超级管理员组 id=1；通过 admin_rule.name=perf_* 定位 perf_* 规则 id 列表
--    实际授权由管理员在 RBAC 配置页面（/admin/group/save）授予；
--    本脚本不写死任何组 ID，避免 id=1 假设。
SELECT 'final_perf_rbac_forward_done' AS result,
  (SELECT GROUP_CONCAT(id) FROM `5kcrm_admin_rule` WHERE name LIKE 'perf_%' AND pid=442) AS perf_rule_ids;
