-- performance_fact table (ASCII only)
CREATE TABLE IF NOT EXISTS `5kcrm_performance_fact` (
  `fact_id` INT(11) NOT NULL AUTO_INCREMENT,
  `perf_id` INT(11) NOT NULL DEFAULT 0,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `period` VARCHAR(10) NOT NULL DEFAULT '',
  `dimension` VARCHAR(30) NOT NULL DEFAULT '' COMMENT 'duty/task/quality/collab',
  `direction` VARCHAR(10) NOT NULL DEFAULT 'positive',
  `fact_type` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'project/task/test/ledger/reward/bid/training/etc',
  `title` VARCHAR(200) NOT NULL DEFAULT '',
  `source_type` VARCHAR(50) NOT NULL DEFAULT '',
  `source_id` VARCHAR(100) NOT NULL DEFAULT '',
  `occurred_time` INT(11) NOT NULL DEFAULT 0,
  `evidence` VARCHAR(500) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending_review',
  `submit_user_id` INT(11) NOT NULL DEFAULT 0,
  `reviewer_user_id` INT(11) NOT NULL DEFAULT 0,
  `review_time` INT(11) NOT NULL DEFAULT 0,
  `review_note` VARCHAR(500) NOT NULL DEFAULT '',
  `create_time` INT(11) NOT NULL DEFAULT 0,
  `update_time` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`fact_id`) USING BTREE,
  UNIQUE KEY `uk_fact_source`(`source_type`,`source_id`,`period`) USING BTREE,
  INDEX `idx_fact_perf`(`perf_id`) USING BTREE,
  INDEX `idx_fact_user_period`(`user_id`,`period`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_general_ci;

SELECT 'performance_fact table applied' AS result;
