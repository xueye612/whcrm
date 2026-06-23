-- Ensure ledger feedback time can be stored independently from register_time.
SET @db_name = DATABASE();
SET @has_col = (
  SELECT COUNT(1)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = '5kcrm_customer_ledger'
    AND COLUMN_NAME = 'feedback_time'
);

SET @ddl = IF(
  @has_col = 0,
  'ALTER TABLE `5kcrm_customer_ledger` ADD COLUMN `feedback_time` INT(11) NOT NULL DEFAULT 0 COMMENT ''反馈时间'' AFTER `register_time`;',
  'SELECT ''skip: feedback_time already exists'';'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
