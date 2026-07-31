'use strict'
// 商机新建/编辑表单修复 + 后端强制覆盖商机组修复 + 奖励页数据源一致性 测试
// 覆盖验收标准：
//   1) 新建商机可以选择启用的商机组
//   2) 编辑商机能看到商机组和当前阶段
//   3) 普通编辑不能绕过"推进商机"直接改变阶段
//   4) 奖励页不硬编码商机组名/阶段名/顺序
// 运行: node crm_web-master/tests/businessTypeForm.test.js
const fs = require('fs'); const path = require('path'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }
const ROOT = path.resolve(__dirname, '..')
const PHP_ROOT = path.resolve(__dirname, '../../crm_php-master')

// ============================================================
// 一、前端 Create.vue 商机组/阶段选择器恢复
// ============================================================
const createVue = fs.readFileSync(path.join(ROOT, 'src/views/crm/business/Create.vue'), 'utf8')

// 1a) HIDDEN_FORM_TYPES 不再隐藏 business_type 和 business_status
check(!/const\s+HIDDEN_FORM_TYPES\s*=\s*\[[^\]]*'business_type'/.test(createVue), 'Create.vue 不再隐藏 business_type form_type')
check(!/const\s+HIDDEN_FORM_TYPES\s*=\s*\[[^\]]*'business_status'/.test(createVue), 'Create.vue 不再隐藏 business_status form_type')

// 1b) 废弃字段 business_status_id 仍然隐藏
check(/'business_status_id'/.test(createVue), 'Create.vue 仍隐藏废弃字段 business_status_id')

// 1c) 真实字段 status_id 不再在 HIDDEN_LEGACY_FIELDS 中隐藏
//     status_id 必须从 HIDDEN_LEGACY_FIELDS 数组中移除
const hiddenBlockMatch = createVue.match(/const HIDDEN_LEGACY_FIELDS = \[([\s\S]*?)\]/)
check(hiddenBlockMatch !== null, 'Create.vue HIDDEN_LEGACY_FIELDS 定义存在')
const hiddenBlock = hiddenBlockMatch ? hiddenBlockMatch[1] : ''
check(!/'status_id'/.test(hiddenBlock), 'Create.vue HIDDEN_LEGACY_FIELDS 不再包含真实 status_id')

// 1d) 模板中 business_type 和 business_status 选择器存在（未被注释）
check(/form_type == 'business_type'/.test(createVue), 'Create.vue 模板保留 business_type 选择器分支')
check(/form_type == 'business_status'/.test(createVue), 'Create.vue 模板保留 business_status 选择器分支')

// 1e) 编辑时阶段选择器禁用（不得通过普通编辑直接改阶段）
check(/form_type === 'business_status'/.test(createVue), 'Create.vue 检测 business_status 阶段')
check(/this\.action\.type === 'update'/.test(createVue), 'Create.vue 编辑模式禁用阶段选择器')

// 1f) 切换商机组后刷新该组阶段（otherChange 处理 business_type）
check(/field\.form_type === 'business_type'/.test(createVue), 'Create.vue otherChange 处理 business_type 切换')
check(/statusItem\.setting = typeObj\.statusList/.test(createVue), 'Create.vue 切换组后刷新阶段列表')

// ============================================================
// 二、后端控制器不再无条件覆盖 type_id / status_id
// ============================================================
const bizCtrlSrc = fs.readFileSync(path.join(PHP_ROOT, 'application/crm/controller/Business.php'), 'utf8')

// 2a) save() 不再无条件 unset type_id / status_id
check(!/unset\(\$param\['type_id'\]\)/.test(bizCtrlSrc), '控制器 save() 不再无条件 unset type_id')
check(!/unset\(\$param\['status_id'\]\)/.test(bizCtrlSrc), '控制器 save() 不再无条件 unset status_id')

// 2b) statusList 缓存修复：移除了"读后即删"的反模式
check(!/cache\(\$key, NULL\)/.test(bizCtrlSrc), 'statusList 不再在读到缓存后立即清除（修复反模式）')

// ============================================================
// 三、后端模型 createData 校验逻辑
// ============================================================
const bizModelSrc = fs.readFileSync(path.join(PHP_ROOT, 'application/crm/model/Business.php'), 'utf8')

// 3a) 有合法 type_id 时尊重用户选择；校验商机组可用
check(/isTypeIdUsable/.test(bizModelSrc), 'Business 模型有 isTypeIdUsable 校验方法')
check(/已停用或无权使用/.test(bizModelSrc), 'createData 校验商机组停用/权限并报错')
// 3b) 未提交 type_id 时才按直签/代理回退
check(/getTypeIdByDealer/.test(bizModelSrc), 'createData 未提交 type_id 时回退到直签/代理默认组')
// 3c) status_id 校验必须属于所选 type_id
check(/所选阶段不属于所选商机组/.test(bizModelSrc), 'createData 校验 status_id 归属 type_id')
// 3d) 未提交 status_id 时使用该组第一个阶段
check(/getFirstStatusId/.test(bizModelSrc), 'createData 未提交 status_id 时使用该组第一个阶段')
// 3e) 经销商只负责推导 signing_method 和 business_category（不推导 type_id）
check(/signing_method.*dealer_signed.*company_direct/.test(bizModelSrc.replace(/\s+/g, ' ')), '经销商推导 signing_method')

// ============================================================
// 四、后端模型 updateDataById 不再由 dealer 无条件覆盖商机组
// ============================================================
// 4a) 不再使用 getTypeIdByDealer 覆盖（保留方法定义但 update 不调用它来覆盖）
const updateSection = bizModelSrc.substring(bizModelSrc.indexOf('function updateDataById'))
check(!/getTypeIdByDealer\(\$param\['dealer_customer_id'\]\)/.test(updateSection), 'updateDataById 不再由 dealer 推导覆盖 type_id')
// 4b) 用户显式提交不同 type_id 时按 order_id 映射；找不到对应阶段则拒绝
check(/按原阶段 order_id 映射新组阶段/.test(updateSection), 'updateDataById 按 order_id 映射新组阶段')
check(/无法切换商机组/.test(updateSection) || /找不到与当前阶段对应的阶段/.test(updateSection), 'updateDataById 找不到对应阶段时拒绝修改')
// 4c) 商机组未变时阶段不得通过普通编辑直接修改
check(/必须走.*推进商机.*接口/.test(updateSection), 'updateDataById 普通编辑不能绕过推进商机改阶段')

// ============================================================
// 五、BusinessStatus 删除保护 + 缓存清除
// ============================================================
const statusModelSrc = fs.readFileSync(path.join(PHP_ROOT, 'application/crm/model/BusinessStatus.php'), 'utf8')

// 5a) 阶段被商机或奖励规则引用时禁止删除，提示引用数量
check(/business_stage_reward_rule.*status_id/.test(statusModelSrc.replace(/\s+/g, ' ')), 'BusinessStatus 编辑时检查奖励规则引用')
check(/不能删除/.test(statusModelSrc), 'BusinessStatus 引用阶段禁止删除并提示')
check(/被.*条商机.*条奖励规则引用/.test(statusModelSrc.replace(/\s+/g, ' ')) || /引用数量/.test(statusModelSrc), 'BusinessStatus 提示引用数量')
// 5b) 商机组删除只做停用（is_display=0, status=0），不物理删除
check(/is_display.*=>.*0.*status.*=>.*0/.test(statusModelSrc.replace(/\s+/g, ' ')), 'BusinessStatus 删除时同时停用 status 和 is_display')
// 5c) 清除缓存
check(/BI_queryCache_StatusList_Data.*NULL/.test(statusModelSrc), 'BusinessStatus 变更后清除状态组缓存')

// ============================================================
// 六、奖励页统一数据源（不硬编码名称/顺序）
// ============================================================
const rewardVue = fs.readFileSync(path.join(ROOT, 'src/views/crm/reward/index.vue'), 'utf8')
// 6a) 不硬编码商机组名映射
check(!/经销商开发.*:.*医院直签/.test(rewardVue), '奖励页不硬编码商机类型名映射')
check(rewardVue.indexOf('stageTypeName') === -1, '奖励页无 stageTypeName 硬编码方法')
// 6b) 使用后端返回的 type_name / stage_name
check(rewardVue.indexOf('r.type_name') !== -1, '奖励页使用后端 type_name')
check(rewardVue.indexOf('r.stage_name') !== -1, '奖励页使用后端 stage_name')
// 6c) 阶段顺序来自后端 stage_order
check(rewardVue.indexOf('stage_order') !== -1, '奖励页阶段顺序来自后端 stage_order')

// ============================================================
// 七、RewardService 统一读取逻辑（关联三表，按 ID 关联）
// ============================================================
const rewardServiceSrc = fs.readFileSync(path.join(PHP_ROOT, 'application/crm/logic/RewardService.php'), 'utf8')
check(rewardServiceSrc.indexOf('__CRM_BUSINESS_TYPE__') !== -1, 'RewardService 关联 crm_business_type')
check(rewardServiceSrc.indexOf('__CRM_BUSINESS_STATUS__') !== -1, 'RewardService 关联 crm_business_status')
check(rewardServiceSrc.indexOf('business_stage_reward_rule') !== -1, 'RewardService 读取 business_stage_reward_rule')
check(rewardServiceSrc.indexOf('stageRewardRuleList') !== -1, 'RewardService 有 stageRewardRuleList 统一读取')

// ============================================================
// 八、推进商机按 type_id + status_id 匹配奖励规则
// ============================================================
check(bizCtrlSrc.indexOf("['type_id' => $businessInfo['type_id'], 'status_id' => $status_id, 'is_enabled' => 1]") !== -1,
  'advance 按 type_id+status_id 读取奖励规则')

// ============================================================
// 九、SQL 恢复脚本存在
// ============================================================
const sqlPath = path.resolve(__dirname, '../../deployment/sql/20260731_biz_type_restore_diagnose.sql')
check(fs.existsSync(sqlPath), '商机组恢复诊断 SQL 脚本存在')
const sqlSrc = fs.readFileSync(sqlPath, 'utf8')
check(sqlSrc.indexOf('SET is_display = 1, status = 1') !== -1, 'SQL 恢复脚本设置 is_display=1, status=1')
check(sqlSrc.indexOf('BI_queryCache_StatusList_Data') !== -1, 'SQL 脚本提及清除应用缓存')
check(sqlSrc.indexOf('不能生成新 ID') !== -1 || sqlSrc.indexOf('不能生成新') !== -1, 'SQL 脚本禁止生成新 status_id')

console.log('商机状态组修复前端测试通过（' + count + ' assertions）')
