-- ============================================================
-- 第六段 forward：补齐 business_stage_reward_rule 规则（严格幂等）
-- ASCII only；MySQL 5.7 兼容
-- 严格幂等：第二次执行不得刷新 update_time；使用 INSERT ... ON DUPLICATE KEY UPDATE
--   仅在字段值不一致时更新，不写 update_time。
-- 经销商开发 type_id=2:
--   status_id=10 基础核实 30
--   status_id=11 有效联系 200
--   status_id=12 正式交流 500
--   status_id=13 明确项目 1000
-- 医院直签 type_id=3 / 医院代理 type_id=4:
--   基础核实 50 / 有效联系 300 / 正式演示 800 / 明确项目 1500
-- 外包项目 type_id=5:
--   基础核实 50 / 正式需求沟通 200 / 方案或报价 200 / 签约阶段不自动生成固定奖励
-- ============================================================

-- 经销商开发 type_id=2
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
VALUES
  (2,10,'商机阶段奖励',30.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (2,11,'商机阶段奖励',200.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (2,12,'商机阶段奖励',500.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (2,13,'商机阶段奖励',1000.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  `source_type`=IF(`source_type`<>VALUES(`source_type`), VALUES(`source_type`), `source_type`),
  `amount`=IF(`amount`<>VALUES(`amount`), VALUES(`amount`), `amount`),
  `rules_version`=IF(`rules_version`<>VALUES(`rules_version`), VALUES(`rules_version`), `rules_version`),
  `is_enabled`=IF(`is_enabled`<>1, 1, `is_enabled`);

-- 医院直签 type_id=3
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
VALUES
  (3,20,'商机阶段奖励',50.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (3,21,'商机阶段奖励',300.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (3,22,'商机阶段奖励',800.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (3,23,'商机阶段奖励',1500.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  `source_type`=IF(`source_type`<>VALUES(`source_type`), VALUES(`source_type`), `source_type`),
  `amount`=IF(`amount`<>VALUES(`amount`), VALUES(`amount`), `amount`),
  `rules_version`=IF(`rules_version`<>VALUES(`rules_version`), VALUES(`rules_version`), `rules_version`),
  `is_enabled`=IF(`is_enabled`<>1, 1, `is_enabled`);

-- 医院代理 type_id=4
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
VALUES
  (4,30,'商机阶段奖励',50.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (4,31,'商机阶段奖励',300.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (4,32,'商机阶段奖励',800.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (4,33,'商机阶段奖励',1500.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  `source_type`=IF(`source_type`<>VALUES(`source_type`), VALUES(`source_type`), `source_type`),
  `amount`=IF(`amount`<>VALUES(`amount`), VALUES(`amount`), `amount`),
  `rules_version`=IF(`rules_version`<>VALUES(`rules_version`), VALUES(`rules_version`), `rules_version`),
  `is_enabled`=IF(`is_enabled`<>1, 1, `is_enabled`);

-- 外包项目 type_id=5
-- 签约阶段 status_id=43 不配置奖励（不自动生成固定奖励）
INSERT INTO `5kcrm_business_stage_reward_rule`
  (`type_id`,`status_id`,`source_type`,`amount`,`rules_version`,`is_enabled`,`create_user_id`,`create_time`,`update_time`)
VALUES
  (5,40,'商机阶段奖励',50.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (5,41,'商机阶段奖励',200.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (5,42,'商机阶段奖励',200.00,'v1',1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  `source_type`=IF(`source_type`<>VALUES(`source_type`), VALUES(`source_type`), `source_type`),
  `amount`=IF(`amount`<>VALUES(`amount`), VALUES(`amount`), `amount`),
  `rules_version`=IF(`rules_version`<>VALUES(`rules_version`), VALUES(`rules_version`), `rules_version`),
  `is_enabled`=IF(`is_enabled`<>1, 1, `is_enabled`);

SELECT 'final_reward_rule_forward_done' AS result;
