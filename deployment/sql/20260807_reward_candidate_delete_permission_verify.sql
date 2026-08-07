-- 奖惩候选删除权限：执行后验证
SELECT COUNT(*) AS delete_permission_count
FROM `5kcrm_admin_rule` child
JOIN `5kcrm_admin_rule` parent ON parent.`id`=child.`pid`
WHERE parent.`types`=2 AND parent.`name`='reward'
  AND child.`types`=2 AND child.`name`='candidatedelete'
  AND child.`level`=3 AND child.`status`=1;

SELECT child.`id`,child.`pid`,child.`name`,child.`title`,child.`level`,child.`status`
FROM `5kcrm_admin_rule` child
JOIN `5kcrm_admin_rule` parent ON parent.`id`=child.`pid`
WHERE parent.`types`=2 AND parent.`name`='reward'
  AND child.`name`='candidatedelete';
