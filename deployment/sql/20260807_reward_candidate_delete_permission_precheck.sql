-- 奖惩候选删除权限：执行前检查（只读）
SELECT `id`,`pid`,`name`,`title`,`level`,`types`,`status`
FROM `5kcrm_admin_rule`
WHERE (`types`=2 AND `name`='reward') OR `name`='candidatedelete'
ORDER BY `level`,`id`;

SELECT COUNT(*) AS reward_parent_count
FROM `5kcrm_admin_rule`
WHERE `types`=2 AND `name`='reward' AND `status`=1;
