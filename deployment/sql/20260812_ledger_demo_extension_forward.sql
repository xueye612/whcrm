-- 台账完成时记录是否需要扩展到演示版本。
-- NULL 用于兼容迁移前已经完成、但从未作出选择的历史台账。
SET @db_name = DATABASE();
SET @has_col = (
  SELECT COUNT(1)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = '5kcrm_customer_ledger'
    AND COLUMN_NAME = 'demo_extension_required'
);

SET @ddl = IF(
  @has_col = 0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD COLUMN `demo_extension_required` TINYINT(1) NULL DEFAULT NULL COMMENT ''完成后是否需要扩展到演示版本：1需要，0不需要，NULL历史未选择'' AFTER `finish_time`;',
  'SELECT ''skip: demo_extension_required already exists'';'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
