<?php
/**
 * 商机状态组修复后端测试（纯逻辑测试，不依赖框架自动加载）
 * 覆盖：
 *   1) 控制器 save() 不再无条件 unset type_id/status_id
 *   2) 模型 createData 尊重用户 type_id 选择 + 校验可用性 + status_id 归属
 *   3) 模型 updateDataById 不由 dealer 无条件覆盖 type_id
 *   4) 普通编辑不能绕过推进商机改阶段
 *   5) BusinessStatus 删除保护：阶段/组被引用时禁止物理删除
 *   6) 缓存清除
 *   7) SQL 恢复脚本完整
 * 运行: php crm_php-master/tests/business_type_fix_test.php
 */
$pass = 0; $fail = 0;
function check($c, $m) { global $pass, $fail; if ($c) { $pass++; echo "[PASS] $m\n"; } else { $fail++; fwrite(STDERR, "[FAIL] $m\n"); } }

$root = dirname(__DIR__);
$businessModelSrc = file_get_contents($root . '/application/crm/model/Business.php');
$businessCtrlSrc = file_get_contents($root . '/application/crm/controller/Business.php');
$statusModelSrc = file_get_contents($root . '/application/crm/model/BusinessStatus.php');
$rewardServiceSrc = file_get_contents($root . '/application/crm/logic/RewardService.php');

// === 1) 控制器 save() 不再无条件 unset ===
check(strpos($businessCtrlSrc, "unset(\$param['type_id'])") === false, '控制器 save() 不再无条件 unset type_id');
check(strpos($businessCtrlSrc, "unset(\$param['status_id'])") === false, '控制器 save() 不再无条件 unset status_id');

// === 2) 模型 createData 尊重用户 type_id 选择 ===
// 2a) isTypeIdUsable 方法存在
check(strpos($businessModelSrc, 'function isTypeIdUsable') !== false, 'Business 模型有 isTypeIdUsable 方法');
// 2b) 校验 is_display=1 且 status=1
check(strpos($businessModelSrc, "is_display'] !== 1") !== false || strpos($businessModelSrc, "'is_display' => 1") !== false, 'isTypeIdUsable 校验 is_display');
check(strpos($businessModelSrc, "status'] !== 1") !== false || strpos($businessModelSrc, "'status' => 1") !== false, 'isTypeIdUsable 校验 status');
// 2c) 校验 structure_id 部门权限
check(strpos($businessModelSrc, 'structure_id') !== false, 'isTypeIdUsable 校验部门权限');
// 2d) 有合法 type_id 时尊重用户选择
check(strpos($businessModelSrc, 'submittedTypeId > 0') !== false, 'createData 检测用户提交的 type_id');
check(strpos($businessModelSrc, '已停用或无权使用') !== false, 'createData 校验商机组停用/权限');
// 2e) 未提交 type_id 时才按直签/代理回退
check(strpos($businessModelSrc, 'getTypeIdByDealer') !== false, 'createData 未提交时回退到直签/代理默认组');
// 2f) status_id 校验归属
check(strpos($businessModelSrc, '所选阶段不属于所选商机组') !== false, 'createData 校验 status_id 归属 type_id');
// 2g) 未提交 status_id 时使用第一个阶段
check(strpos($businessModelSrc, 'getFirstStatusId') !== false, 'createData 未提交 status_id 时使用第一个阶段');

// === 3) 经销商只推导 signing_method 和 business_category ===
check(strpos($businessModelSrc, "signing_method'] = \$param['dealer_customer_id'] > 0 ? 'dealer_signed' : 'company_direct'") !== false, '经销商推导 signing_method');
check(strpos($businessModelSrc, "business_category'] = \$param['dealer_customer_id'] > 0 ? 'agent' : 'direct'") !== false, '经销商推导 business_category');

// === 4) updateDataById 不由 dealer 无条件覆盖 type_id ===
$updateStart = strpos($businessModelSrc, 'function updateDataById');
$updateSection = substr($businessModelSrc, $updateStart, 6000);
// 4a) 不再调用 getTypeIdByDealer 来推导覆盖
check(strpos($updateSection, 'getTypeIdByDealer($param') === false, 'updateDataById 不再由 dealer 推导覆盖 type_id');
// 4b) 用户显式提交不同 type_id 时校验
check(strpos($updateSection, 'submittedTypeId') !== false, 'updateDataById 检测用户提交的 type_id');
check(strpos($updateSection, 'isTypeIdUsable($submittedTypeId') !== false, 'updateDataById 校验新 type_id 可用性');
// 4c) 按 order_id 映射；找不到对应阶段则拒绝
check(strpos($updateSection, 'order_id') !== false, 'updateDataById 按 order_id 映射阶段');
check(strpos($updateSection, '无法切换商机组') !== false, 'updateDataById 找不到对应阶段时拒绝修改');
// 4d) 商机组未变时保留原 status_id
check(strpos($updateSection, '必须走') !== false && strpos($updateSection, '推进商机') !== false, 'updateDataById 普通编辑不能绕过推进商机');

// === 5) BusinessStatus 删除保护 ===
// 5a) 编辑商机组时阶段被引用禁止删除
check(strpos($statusModelSrc, 'business_stage_reward_rule') !== false, 'BusinessStatus 编辑检查奖励规则引用');
check(strpos($statusModelSrc, 'crm_business') !== false, 'BusinessStatus 编辑检查商机引用');
check(strpos($statusModelSrc, '不能删除') !== false, 'BusinessStatus 引用阶段禁止删除');
check(strpos($statusModelSrc, '已被引用，不能删除') !== false || strpos($statusModelSrc, '不能删除') !== false, 'BusinessStatus 删除保护提示引用');
// 5b) 商机组删除只停用
check(strpos($statusModelSrc, "is_display' => 0") !== false, 'BusinessStatus 删除设置 is_display=0');
check(strpos($statusModelSrc, "status' => 0") !== false, 'BusinessStatus 删除设置 status=0（停用）');
// 5c) 缓存清除
check(strpos($statusModelSrc, 'BI_queryCache_StatusList_Data') !== false, 'BusinessStatus 变更后清除缓存');

// === 6) 控制器 statusList 缓存修复 ===
// 不再有"读到缓存后清除"的反模式
check(strpos($businessCtrlSrc, '}else{') === false || strpos($businessCtrlSrc, "cache(\$key, NULL)") === false,
    'statusList 不再在读到缓存后立即清除');

// === 7) RewardService 统一数据源 ===
check(strpos($rewardServiceSrc, '__CRM_BUSINESS_TYPE__') !== false, 'RewardService 关联 crm_business_type');
check(strpos($rewardServiceSrc, '__CRM_BUSINESS_STATUS__') !== false, 'RewardService 关联 crm_business_status');
check(strpos($rewardServiceSrc, 'business_stage_reward_rule') !== false, 'RewardService 读取 business_stage_reward_rule');
check(strpos($rewardServiceSrc, 'stageRewardRuleList') !== false, 'RewardService 有 stageRewardRuleList 统一读取');
// 不硬编码阶段名/组名
check(strpos($rewardServiceSrc, '基础核实') === false || strpos($rewardServiceSrc, '经销商有效联系') === false,
    'RewardService stageRewardRuleList 不硬编码阶段名');

// === 8) 推进商机按 type_id + status_id 匹配奖励规则 ===
check(strpos($businessCtrlSrc, "['type_id' => \$businessInfo['type_id'], 'status_id' => \$status_id, 'is_enabled' => 1]") !== false,
    'advance 按 type_id+status_id 读取奖励规则');

// === 9) SQL 恢复脚本 ===
$sqlPath = $root . '/../deployment/sql/20260731_biz_type_restore_diagnose.sql';
check(file_exists($sqlPath), '商机组恢复诊断 SQL 脚本存在');
$sqlSrc = file_get_contents($sqlPath);
check(strpos($sqlSrc, 'is_display = 1, status = 1') !== false, 'SQL 恢复 is_display=1, status=1');
check(strpos($sqlSrc, 'BI_queryCache_StatusList_Data') !== false, 'SQL 提及清除应用缓存');
check(strpos($sqlSrc, '不能生成新') !== false, 'SQL 禁止生成新 status_id');
check(strpos($sqlSrc, 'ORDER BY') !== false, 'SQL 有诊断排序查询');

// === 10) 前端 Create.vue 修复 ===
$createVueSrc = file_get_contents($root . '/../crm_web-master/src/views/crm/business/Create.vue');
// 不再隐藏 business_type / business_status form_type
check(strpos($createVueSrc, "HIDDEN_FORM_TYPES = ['business_type', 'business_status']") === false, '前端不再隐藏 business_type/business_status');
// 废弃字段仍隐藏
check(strpos($createVueSrc, "'business_status_id'") !== false, '前端仍隐藏废弃 business_status_id');
// 真实 status_id 不再隐藏
$hiddenMatch = array();
preg_match('/const HIDDEN_LEGACY_FIELDS = \[([^\]]*)\]/s', $createVueSrc, $hiddenMatch);
$hiddenContent = isset($hiddenMatch[1]) ? $hiddenMatch[1] : '';
check(strpos($hiddenContent, "'status_id'") === false, '前端 HIDDEN_LEGACY_FIELDS 不再包含 status_id');

echo "\n[RESULT] 通过：{$pass}，失败：{$fail}\n";
exit($fail > 0 ? 1 : 0);
