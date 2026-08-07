-- 合作企业有效联系与数据批次奖励规则。
-- 主流程：初筛 -> 已核实 -> 有效联系 -> 洽谈中 -> 已合作。
-- 原始数据仍在线下底稿管理，仅复用奖励候选审核，不新增数据批次业务模块。

UPDATE `5kcrm_admin_field`
   SET `input_tips` = '主流程：初筛→已核实→有效联系→洽谈中→已合作；有效联系需有客户活动依据',
       `setting` = '初筛\n已核实\n有效联系\n洽谈中\n已合作\n暂缓\n不适合\n无法联系',
       `options` = '初筛,已核实,有效联系,洽谈中,已合作,暂缓,不适合,无法联系',
       `update_time` = UNIX_TIMESTAMP()
 WHERE `types` = 'crm_customer'
   AND `field` = 'cooperation_stage';

INSERT INTO `5kcrm_reward_manual_rule`
(`rule_code`,`category`,`rule_name`,`direction`,`amount`,`calc_mode`,`amount_min`,`amount_max`,`pool_pct`,`description`,`is_enabled`,`sort_order`,`create_user_id`,`update_user_id`,`create_time`,`update_time`)
SELECT
 'raw_data_batch_basic','数据池质量','高质量原始数据基础批次','reward',100.00,'fixed',0,0,0,
 '当月不少于10个真实、去重、来源明确且初步匹配的对象，其中不少于3个进入基础核实。必须提交线下底稿及去重、来源、匹配和核实依据；虚假、重复或凑数可取消整批。',
 1,180,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()
WHERE NOT EXISTS (
 SELECT 1 FROM `5kcrm_reward_manual_rule` WHERE `rule_code`='raw_data_batch_basic'
);

INSERT INTO `5kcrm_reward_manual_rule`
(`rule_code`,`category`,`rule_name`,`direction`,`amount`,`calc_mode`,`amount_min`,`amount_max`,`pool_pct`,`description`,`is_enabled`,`sort_order`,`create_user_id`,`update_user_id`,`create_time`,`update_time`)
SELECT
 'raw_data_batch_premium','数据池质量','高质量原始数据优质批次','reward',200.00,'fixed',0,0,0,
 '当月不少于20个信息完整且无明显无效对象的数据，其中不少于5个进入基础核实且至少1个进入有效联系。必须提交线下底稿及去重、来源、匹配和核实依据；虚假、重复或凑数可取消整批。',
 1,181,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()
WHERE NOT EXISTS (
 SELECT 1 FROM `5kcrm_reward_manual_rule` WHERE `rule_code`='raw_data_batch_premium'
);

-- 同一人员同一月份只生成一个 source_ref：raw_batch:user:{id}:month:{YYYY-MM}。
-- 从基础档升级到优质档时更新原候选并重新进入待审核，不叠加生成第二条。
