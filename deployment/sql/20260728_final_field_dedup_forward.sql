-- ============================================================
-- 第四段 forward：删除重复自定义字段（硬阻断 + 严格幂等）
-- ASCII only；MySQL 5.7 兼容
-- 硬阻断规则（forward 内部）：
--   H1) crm_business_data 存在 field='crm_rianjp' 数据 -> 必须停止且不删除
--   H2) crm_business_data 存在 field='dealer_customer_id' 数据 -> 必须停止且不删除
--   H3) crm_business.dealer_customer_id 物理列存在且有待迁移关系（!=0 的行）-> 必须停止
-- 注意：不删除 crm_business.dealer_customer_id 物理列
-- ============================================================

SET @block_biz_data_rianjp := (SELECT COUNT(*) FROM `5kcrm_crm_business_data` WHERE field='crm_rianjp');
SET @block_biz_data_dealer := (SELECT COUNT(*) FROM `5kcrm_crm_business_data` WHERE field='dealer_customer_id');
SET @block_dealer_id_pending := (SELECT COUNT(*) FROM `5kcrm_crm_business` WHERE dealer_customer_id<>0);

SELECT 'H1_biz_data_rianjp' AS step, @block_biz_data_rianjp AS cnt;
SELECT 'H2_biz_data_dealer' AS step, @block_biz_data_dealer AS cnt;
SELECT 'H3_dealer_id_pending' AS step, @block_dealer_id_pending AS cnt;

SET @any_block := @block_biz_data_rianjp + @block_biz_data_dealer + @block_dealer_id_pending;

-- 1) 删除 admin_field 中的两条定义（仅当无硬阻断时）
SET @ddl_field952 := IF(@any_block = 0,
  'DELETE FROM `5kcrm_admin_field` WHERE field_id=952 AND field=''crm_rianjp''',
  'SELECT ''BLOCKED_final_field_dedup_952'' AS note');
PREPARE s952 FROM @ddl_field952; EXECUTE s952; DEALLOCATE PREPARE s952;

SET @ddl_field962 := IF(@any_block = 0,
  'DELETE FROM `5kcrm_admin_field` WHERE field_id=962 AND field=''dealer_customer_id''',
  'SELECT ''BLOCKED_final_field_dedup_962'' AS note');
PREPARE s962 FROM @ddl_field962; EXECUTE s962; DEALLOCATE PREPARE s962;

-- 2) 删除 crm_business_data 中残留扩展记录（仅当无硬阻断时；precheck 已确认无数据，但 forward 再验一次）
SET @ddl_bd_rianjp := IF(@any_block = 0,
  'DELETE FROM `5kcrm_crm_business_data` WHERE field=''crm_rianjp''',
  'SELECT ''BLOCKED_final_field_dedup_bd_rianjp'' AS note');
PREPARE sbd1 FROM @ddl_bd_rianjp; EXECUTE sbd1; DEALLOCATE PREPARE sbd1;

SET @ddl_bd_dealer := IF(@any_block = 0,
  'DELETE FROM `5kcrm_crm_business_data` WHERE field=''dealer_customer_id''',
  'SELECT ''BLOCKED_final_field_dedup_bd_dealer'' AS note');
PREPARE sbd2 FROM @ddl_bd_dealer; EXECUTE sbd2; DEALLOCATE PREPARE sbd2;

-- 3) 不删除 crm_business.dealer_customer_id 物理列
--    Business::save/update 中通过 extForm 路径写入，保留供医院代理使用
--    前端 Create.vue 仅在 hospital_agent + dealer_signed 时显示经销商选择器
--    所有其他情况下页面只有一个中文"所属经销商"标签或被隐藏

SELECT 'final_field_dedup_forward_done' AS result, @any_block AS blocked;
