-- 合作企业绩效维度与业务获取奖金池阶段预发规则补齐。
-- 金额口径由 RewardService 固定政策维护：
--   基础核实30元：独立即时奖励，不参与业务获取奖金池抵扣；
--   有效联系200元、正式交流500元：阶段预发，最终奖金池结算时抵扣。

UPDATE `5kcrm_performance_fact`
   SET `dimension` = 'task', `update_time` = UNIX_TIMESTAMP()
 WHERE `source_type` IN (
     'cooperation_customer_verify',
     'cooperation_customer_contact',
     'cooperation_customer_formal_exchange'
 )
   AND `dimension` <> 'task';
