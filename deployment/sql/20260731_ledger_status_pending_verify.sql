-- 验证台账历史状态已完成归并；结果应为 0。

SELECT COUNT(*) AS legacy_status_count
FROM `5kcrm_customer_ledger`
WHERE `status` = '待验证';
