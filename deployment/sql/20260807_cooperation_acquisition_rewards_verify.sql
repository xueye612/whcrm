-- 合作企业绩效节点归类校验：返回 OK 表示不存在误归入其他维度的记录。
SELECT IF(COUNT(*) = 0, 'OK', 'FAIL') AS cooperation_fact_dimension
  FROM `5kcrm_performance_fact`
 WHERE `source_type` IN (
     'cooperation_customer_verify',
     'cooperation_customer_contact',
     'cooperation_customer_formal_exchange'
 )
   AND `dimension` <> 'task';

-- 三类奖励必须保持独立来源；基础核实不会被奖金池抵扣查询纳入。
SELECT `source_type`, COUNT(*) AS candidate_count, SUM(`amount`) AS amount_total
  FROM `5kcrm_reward_candidate`
 WHERE `source_type` IN ('经销商基础核实', '经销商有效联系', '经销商正式交流')
 GROUP BY `source_type`;
