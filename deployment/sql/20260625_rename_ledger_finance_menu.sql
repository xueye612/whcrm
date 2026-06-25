-- 菜单/权限名称：客户台账 -> 台账，收支管理 -> 收支
-- 右侧 CRM 侧边栏文案来自前端路由 meta.title（crm.js），本脚本用于后台「菜单管理 / 角色权限」展示名称同步。

UPDATE `5kcrm_admin_menu`
SET `title` = '台账'
WHERE `module` = 'ledger' AND `title` = '客户台账';

UPDATE `5kcrm_admin_menu`
SET `title` = '收支'
WHERE `module` = 'finance' AND `title` = '收支管理';

UPDATE `5kcrm_admin_rule`
SET `title` = '台账'
WHERE `name` = 'ledger' AND `level` = 1 AND `title` = '客户台账';

UPDATE `5kcrm_admin_rule`
SET `title` = '收支'
WHERE `name` = 'finance' AND `level` = 1 AND `title` = '收支管理';

-- 日历类型（若存在旧名称）
UPDATE `5kcrm_admin_oa_schedule`
SET `name` = '台账'
WHERE `name` = '客户台账';
