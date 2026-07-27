-- =============================================================================
-- P-CONFIG 奖励/绩效可配置项：未确认金额、月度上限、池拆分(70/30)等做成后台可配置。
-- 未配置(NULL)时业务必须显示"待配置"并禁止生成奖励/结算/自动计算；不得编造或伪装。
-- 幂等：可重复执行。
-- =============================================================================
CREATE TABLE IF NOT EXISTS `5kcrm_reward_config` (
  `config_key` VARCHAR(50) NOT NULL,
  `config_value` TEXT,
  `config_desc` VARCHAR(200) NOT NULL DEFAULT '',
  `update_user_id` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`config_key`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci COMMENT='奖励/绩效可配置项';

-- 仅插入未确认项的占位（值为 NULL=待配置）。已确认口径(900/900/200/200、2%/3%、40/28/25/5/2)在代码常量中，不入此表。
INSERT IGNORE INTO `5kcrm_reward_config` (`config_key`,`config_value`,`config_desc`) VALUES
  ('monthly_cap_amount',      NULL, '单人月度奖励上限金额；未配置=待配置(不启用硬限制)'),
  ('dealer_first_payment_reward', NULL, '经销商首期回款奖励金额；未配置=待配置'),
  ('hospital_stage_rewards',  NULL, '医院各阶段奖励(JSON)；未配置=待配置'),
  ('outsource_pool_split',    NULL, '外包奖金池拆分模式(如70/30)；未配置=待配置');

SELECT 'reward_config applied' AS result;
