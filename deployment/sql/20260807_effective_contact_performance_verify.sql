SELECT `setting`,`options`,`input_tips`
  FROM `5kcrm_admin_field`
 WHERE `types`='crm_customer' AND `field`='cooperation_stage';

SELECT `rule_code`,`rule_name`,`amount`,`is_enabled`,`description`
  FROM `5kcrm_reward_manual_rule`
 WHERE `rule_code` IN ('raw_data_batch_basic','raw_data_batch_premium')
 ORDER BY `amount`;

SELECT IF(COUNT(*)=2, 'OK_batch_reward_rules', 'FAIL_batch_reward_rules') AS result
  FROM `5kcrm_reward_manual_rule`
 WHERE `rule_code` IN ('raw_data_batch_basic','raw_data_batch_premium')
   AND `is_enabled`=1;
