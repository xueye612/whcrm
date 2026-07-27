-- pcorrect unique indexes (idempotent)
SET @s1 = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='5kcrm_stage_offset' AND INDEX_NAME='uk_so_batch_user_project')=0,
  'ALTER TABLE `5kcrm_stage_offset` ADD UNIQUE INDEX `uk_so_batch_user_project`(`batch_id`,`user_id`,`project_ref`)', 'SELECT 1');
PREPARE stmt1 FROM @s1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;
SELECT 'pcorrect unique indexes applied' AS result;
