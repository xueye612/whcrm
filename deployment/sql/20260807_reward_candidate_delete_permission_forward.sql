-- 奖惩候选删除权限：幂等安装
-- 超级管理员无需角色授权；其他人员可在角色权限中勾选“删除候选”。
SET @reward_parent_id := (
  SELECT `id` FROM `5kcrm_admin_rule`
  WHERE `types`=2 AND `name`='reward' AND `status`=1
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `5kcrm_admin_rule` (`types`,`title`,`name`,`level`,`pid`,`status`)
SELECT 2,'删除候选','candidatedelete',3,@reward_parent_id,1
FROM DUAL
WHERE @reward_parent_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `5kcrm_admin_rule`
    WHERE `types`=2 AND `pid`=@reward_parent_id AND `name`='candidatedelete'
  );

SELECT IF(@reward_parent_id IS NULL, 'BLOCKED_REWARD_PARENT_NOT_FOUND', 'OK') AS migration_result;
