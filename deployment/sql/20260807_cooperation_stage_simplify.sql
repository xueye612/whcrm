-- 合作阶段精简：联系行为复用CRM活动记录，不再作为独立合作阶段。
-- 历史“已联系”按已确认的业务口径归并为“已核实”。

UPDATE `5kcrm_crm_customer`
   SET `cooperation_stage` = '已核实',
       `update_time` = UNIX_TIMESTAMP()
 WHERE `cooperation_stage` = '已联系';

UPDATE `5kcrm_admin_field`
   SET `input_tips` = '联系行为使用活动记录；本字段只记录实质合作进展',
       `setting` = '初筛\n已核实\n洽谈中\n已合作\n暂缓\n不适合\n无法联系',
       `options` = '初筛,已核实,洽谈中,已合作,暂缓,不适合,无法联系',
       `update_time` = UNIX_TIMESTAMP()
 WHERE `types` = 'crm_customer'
   AND `field` = 'cooperation_stage';

-- 验证：两条查询都应返回0。
SELECT COUNT(*) AS legacy_contacted_customers
  FROM `5kcrm_crm_customer`
 WHERE `cooperation_stage` = '已联系';

SELECT COUNT(*) AS legacy_contacted_options
  FROM `5kcrm_admin_field`
 WHERE `types` = 'crm_customer'
   AND `field` = 'cooperation_stage'
   AND (`setting` LIKE '%已联系%' OR `options` LIKE '%已联系%');
