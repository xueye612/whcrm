-- ============================================================
-- 第三段 forward：商机阶段中文化（原地 UPDATE，保留 status_id，严格幂等）
-- ASCII only；MySQL 5.7 兼容
-- 严格幂等：仅在名称与目标不一致时 UPDATE；不刷新 update_time。
-- ============================================================

UPDATE `5kcrm_crm_business_status` SET `name`='基础核实'   WHERE status_id=10 AND (`name` IS NULL OR `name`<>'基础核实');
UPDATE `5kcrm_crm_business_status` SET `name`='有效联系'   WHERE status_id=11 AND (`name` IS NULL OR `name`<>'有效联系');
UPDATE `5kcrm_crm_business_status` SET `name`='正式交流'   WHERE status_id=12 AND (`name` IS NULL OR `name`<>'正式交流');
UPDATE `5kcrm_crm_business_status` SET `name`='明确项目'   WHERE status_id=13 AND (`name` IS NULL OR `name`<>'明确项目');

UPDATE `5kcrm_crm_business_status` SET `name`='基础核实'   WHERE status_id=20 AND (`name` IS NULL OR `name`<>'基础核实');
UPDATE `5kcrm_crm_business_status` SET `name`='有效联系'   WHERE status_id=21 AND (`name` IS NULL OR `name`<>'有效联系');
UPDATE `5kcrm_crm_business_status` SET `name`='正式演示'   WHERE status_id=22 AND (`name` IS NULL OR `name`<>'正式演示');
UPDATE `5kcrm_crm_business_status` SET `name`='明确项目'   WHERE status_id=23 AND (`name` IS NULL OR `name`<>'明确项目');

UPDATE `5kcrm_crm_business_status` SET `name`='基础核实'   WHERE status_id=30 AND (`name` IS NULL OR `name`<>'基础核实');
UPDATE `5kcrm_crm_business_status` SET `name`='有效联系'   WHERE status_id=31 AND (`name` IS NULL OR `name`<>'有效联系');
UPDATE `5kcrm_crm_business_status` SET `name`='正式演示'   WHERE status_id=32 AND (`name` IS NULL OR `name`<>'正式演示');
UPDATE `5kcrm_crm_business_status` SET `name`='明确项目'   WHERE status_id=33 AND (`name` IS NULL OR `name`<>'明确项目');

UPDATE `5kcrm_crm_business_status` SET `name`='基础核实'   WHERE status_id=40 AND (`name` IS NULL OR `name`<>'基础核实');
UPDATE `5kcrm_crm_business_status` SET `name`='正式需求沟通' WHERE status_id=41 AND (`name` IS NULL OR `name`<>'正式需求沟通');
UPDATE `5kcrm_crm_business_status` SET `name`='方案或报价' WHERE status_id=42 AND (`name` IS NULL OR `name`<>'方案或报价');
UPDATE `5kcrm_crm_business_status` SET `name`='签约'       WHERE status_id=43 AND (`name` IS NULL OR `name`<>'签约');

-- 系统终态保持中文
UPDATE `5kcrm_crm_business_status` SET `name`='赢单' WHERE status_id=1 AND (`name` IS NULL OR `name`<>'赢单');
UPDATE `5kcrm_crm_business_status` SET `name`='输单' WHERE status_id=2 AND (`name` IS NULL OR `name`<>'输单');
UPDATE `5kcrm_crm_business_status` SET `name`='无效' WHERE status_id=3 AND (`name` IS NULL OR `name`<>'无效');

SELECT 'final_biz_status_forward_done' AS result;
