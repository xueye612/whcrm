-- 合作企业线索最小化改造。
-- 不新增菜单或业务子表，只为现有客户增加 7 个可空字段。

ALTER TABLE `5kcrm_crm_customer`
  ADD COLUMN `cooperation_type` VARCHAR(50) NULL DEFAULT NULL COMMENT '客户类型',
  ADD COLUMN `cooperation_stage` VARCHAR(20) NULL DEFAULT NULL COMMENT '合作阶段',
  ADD COLUMN `discover_user_id` INT(11) NULL DEFAULT NULL COMMENT '发现人',
  ADD COLUMN `verify_user_id` INT(11) NULL DEFAULT NULL COMMENT '核实人',
  ADD COLUMN `verify_time` INT(11) NULL DEFAULT NULL COMMENT '核实时间',
  ADD COLUMN `verify_result` VARCHAR(20) NULL DEFAULT NULL COMMENT '核实结果',
  ADD COLUMN `verify_note` TEXT NULL COMMENT '核实说明';

INSERT INTO `5kcrm_admin_field`
(`types`,`types_id`,`field`,`name`,`form_type`,`default_value`,`max_length`,`is_unique`,`is_null`,`input_tips`,`setting`,`order_id`,`operating`,`create_time`,`update_time`,`type`,`relevant`,`is_hidden`,`style_percent`,`form_position`,`precisions`,`max_num_restrict`,`min_num_restrict`,`remark`,`options`,`formAssistId`)
VALUES
('crm_customer',0,'cooperation_type','客户类型','select','',0,0,0,'','医院客户\n代理商\n软件厂商\n渠道合作方\n系统集成商\n区域服务商\n其他合作企业',20,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),3,'',0,50,'6,0',2,'','','','医院客户,代理商,软件厂商,渠道合作方,系统集成商,区域服务商,其他合作企业',3001),
('crm_customer',0,'cooperation_stage','合作阶段','select','',0,0,0,'联系行为使用活动记录；本字段只记录实质合作进展','初筛\n已核实\n洽谈中\n已合作\n暂缓\n不适合\n无法联系',21,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),3,'',0,50,'6,1',2,'','','','初筛,已核实,洽谈中,已合作,暂缓,不适合,无法联系',3002),
('crm_customer',0,'discover_user_id','发现人','single_user','',0,0,0,'','',22,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),0,'',0,50,'7,0',NULL,'','','','',3003),
('crm_customer',0,'verify_user_id','核实人','single_user','',0,0,0,'','',23,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),0,'',0,50,'7,1',NULL,'','','','',3004),
('crm_customer',0,'verify_time','核实时间','datetime','',0,0,0,'','',24,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),13,'',0,50,'8,0',0,'','','','',3005),
('crm_customer',0,'verify_result','核实结果','select','',0,0,0,'','推荐跟进\n储备观察\n不建议联系',25,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),3,'',0,50,'8,1',2,'','','','推荐跟进,储备观察,不建议联系',3006),
('crm_customer',0,'verify_note','核实说明','textarea','',500,0,0,'请填写核实依据；无实质依据的记录不应进入绩效审核','',26,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),2,'',0,100,'9,0',0,'','','','',3007);

-- 并发最终保护：同一来源对象全生命周期只能有一条绩效事实。
-- 执行前必须确认 precheck 的来源重复查询返回 0 行。
ALTER TABLE `5kcrm_performance_fact`
  DROP INDEX `uk_fact_source`,
  ADD UNIQUE KEY `uk_fact_source` (`source_type`, `source_id`);
