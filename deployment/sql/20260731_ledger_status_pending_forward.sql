-- 台账状态精简：废弃「待验证」，历史数据统一归入「待处理」。
-- 幂等：重复执行不会产生额外变更。

UPDATE `5kcrm_customer_ledger`
SET `status` = '待处理'
WHERE `status` = '待验证';
