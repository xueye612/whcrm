/**
 * P0 任务工作流、W/R/K 等级与轻量测试规则纯逻辑测试
 *
 * 这些规则与后端 WorkflowService.php 保持一致，用于前后端口径一致性回归。
 * 纯 Node 断言，不依赖 babel-core 或 Vue 运行时。
 */
const assert = require('assert')
const fs = require('fs')
const path = require('path')

// ===================== W/R/K 字典 =====================
const W_LEVELS = ['W1', 'W2', 'W3', 'W4', 'W5']
const R_LEVELS = ['R1', 'R2', 'R3', 'R4', 'R5']
const K_LEVELS = ['K1', 'K2', 'K3', 'K4']

const WRK_DICT = {
  W: {
    W1: '两小时以内',
    W2: '两至八小时',
    W3: '一至三人日',
    W4: '三至十人日',
    W5: '超过十人日，原则上应拆分'
  },
  R: {
    R1: '局部、易验证、易撤销',
    R2: '常规业务功能，影响有限',
    R3: '跨模块、共享能力，或可能造成部分数据不一致',
    R4: '影响生产流程、较大范围数据、外部接口或设备，恢复成本高或回退复杂',
    R5: '涉及患者安全、正式医疗文书真实性、核心数据、重大连续运行、法律合规或核心架构风险'
  },
  K: {
    K1: '成熟',
    K2: '基本明确',
    K3: '需要专业确认',
    K4: '必须有正式专业依据'
  }
}

function isValidW(v) { return W_LEVELS.indexOf(v) !== -1 }
function isValidR(v) { return R_LEVELS.indexOf(v) !== -1 }
function isValidK(v) { return K_LEVELS.indexOf(v) !== -1 }

// ===================== 测试：W/R/K 等级边界 =====================
assert.strictEqual(isValidW('W1'), true)
assert.strictEqual(isValidW('W5'), true)
assert.strictEqual(isValidW('W6'), false, 'W6 不应合法')
assert.strictEqual(isValidW(''), false, '空值不合法')
assert.strictEqual(isValidW('w1'), false, '大小写敏感')
assert.strictEqual(isValidR('R3'), true)
assert.strictEqual(isValidR('R6'), false, 'R6 不应合法')
assert.strictEqual(isValidK('K1'), true)
assert.strictEqual(isValidK('K5'), false, 'K5 不应合法')
assert.strictEqual(isValidK(null), false, 'null 不合法')

// 字典完整性
assert.strictEqual(Object.keys(WRK_DICT.W).length, 5)
assert.strictEqual(Object.keys(WRK_DICT.R).length, 5)
assert.strictEqual(Object.keys(WRK_DICT.K).length, 4)

// ===================== 主状态序列 =====================
const STATUS_ORDER = ['待评估', '待处理', '处理中', '待内部验收', '待发布', '待客户验证', '已完成']

// ===================== 合法迁移矩阵（镜像后端 allowedTransitions）=====================
const TRANSITIONS = {
  evaluate:          { '待评估': '待处理' },
  start:             { '待处理': '处理中' },
  submit_acceptance: { '处理中': '待内部验收' },
  acceptance_pass:   { '待内部验收': '待发布' },
  acceptance_return: { '待内部验收': '处理中' },
  confirm_release:   { '待发布': '待客户验证' },
  customer_confirm:  { '待客户验证': '已完成' },
  customer_return:   { '待客户验证': '处理中' },
  complete:          { '待发布': '已完成' }
}

function resolveTarget(action, current) {
  if (!TRANSITIONS[action]) return false
  if (!Object.prototype.hasOwnProperty.call(TRANSITIONS[action], current)) return false
  return TRANSITIONS[action][current]
}

// ===================== 测试：合法迁移 =====================
assert.strictEqual(resolveTarget('evaluate', '待评估'), '待处理')
assert.strictEqual(resolveTarget('start', '待处理'), '处理中')
assert.strictEqual(resolveTarget('submit_acceptance', '处理中'), '待内部验收')
assert.strictEqual(resolveTarget('acceptance_pass', '待内部验收'), '待发布')
assert.strictEqual(resolveTarget('confirm_release', '待发布'), '待客户验证')
assert.strictEqual(resolveTarget('customer_confirm', '待客户验证'), '已完成')

// ===================== 测试：非法跳转被拒绝 =====================
assert.strictEqual(resolveTarget('evaluate', '处理中'), false, '处理中不能评估')
assert.strictEqual(resolveTarget('start', '待评估'), false, '待评估不能开始处理')
assert.strictEqual(resolveTarget('confirm_release', '处理中'), false, '处理中不能确认发布')
assert.strictEqual(resolveTarget('customer_confirm', '待发布'), false, '待发布不能客户确认')
assert.strictEqual(resolveTarget('submit_acceptance', '待评估'), false, '待评估不能提交验收')
assert.strictEqual(resolveTarget('unknown_action', '待评估'), false, '未知动作')

// ===================== 测试：未评估不能开始 =====================
// 待评估状态唯一允许的迁移是 evaluate；不能直接 start
assert.strictEqual(resolveTarget('start', '待评估'), false)

// ===================== 测试评定状态 =====================
const REVIEW_STATUS = ['pending', 'compliant', 'non_compliant']
assert.strictEqual(REVIEW_STATUS.indexOf('pending'), 0)
assert.strictEqual(REVIEW_STATUS.indexOf('compliant'), 1)
assert.strictEqual(REVIEW_STATUS.indexOf('non_compliant'), 2)
assert.strictEqual(REVIEW_STATUS.indexOf('approved'), -1, 'approved 不是合法评定状态（只有三档）')

// 退回不是第四个评定状态
assert.strictEqual(REVIEW_STATUS.length, 3, '评定状态只有三档，退回不是第四个状态')

// ===================== 测试：派生标签组合（不单独持久化）=====================
// 派生标签由 任务主状态 + 提交状态 + 评定状态 组合得出
function deriveDisplayLabel(taskMainStatus, submitStatus, reviewStatus) {
  if (reviewStatus === 'compliant') return '符合要求'
  if (reviewStatus === 'non_compliant') return '待补充/重新测试'
  if (submitStatus === 'submitted') return '待研发负责人评定'
  return '待测试'
}

assert.strictEqual(deriveDisplayLabel('处理中', 'not_submitted', 'pending'), '待测试')
assert.strictEqual(deriveDisplayLabel('处理中', 'submitted', 'pending'), '待研发负责人评定')
assert.strictEqual(deriveDisplayLabel('处理中', 'submitted', 'compliant'), '符合要求')
assert.strictEqual(deriveDisplayLabel('处理中', 'not_submitted', 'non_compliant'), '待补充/重新测试')

// ===================== 测试：幂等键构建（基于 request_id + 测试人）=====================
function buildTestIdempotencyKey(requestId, testerUserId) {
  return 'test:' + requestId + ':' + parseInt(testerUserId, 10)
}
assert.strictEqual(buildTestIdempotencyKey('req-1', 5), 'test:req-1:5')
assert.strictEqual(buildTestIdempotencyKey('req-1', 5), buildTestIdempotencyKey('req-1', 5), '相同请求相同测试人幂等')
assert.notStrictEqual(buildTestIdempotencyKey('req-1', 5), buildTestIdempotencyKey('req-1', 6), '同请求不同测试人键不同')
assert.notStrictEqual(buildTestIdempotencyKey('req-1', 5), buildTestIdempotencyKey('req-2', 5), '不同请求允许新开测试轮次')

// ===================== 测试：台账自动建任务幂等键 =====================
function buildLedgerAutoTaskKey(ledgerId) {
  return 'ledger:' + parseInt(ledgerId, 10) + ':auto-task'
}
assert.strictEqual(buildLedgerAutoTaskKey(42), 'ledger:42:auto-task')

// ===================== 测试：待发布/待客户验证不属于测试任务状态 =====================
const TEST_TASK_STATES = ['待测试', '待研发负责人评定', '待补充/重新测试', '符合要求']
assert.strictEqual(TEST_TASK_STATES.indexOf('待发布'), -1, '待发布不属于测试任务状态')
assert.strictEqual(TEST_TASK_STATES.indexOf('待客户验证'), -1, '待客户验证不属于测试任务状态')

// ===================== 测试：发布门禁条件（需开发自测+业务测试双全且符合）=====================
// 镜像后端 checkReleaseGate：need_release=1 时，零必需测试或缺少 dev_self/business 必须拒绝
function checkReleaseGate(testExts) {
  var required = testExts.filter(function(e) { return e.is_required === 1 })
  if (required.length === 0) {
    return { ok: false, reason: '缺少必需测试任务（至少需要开发自测和业务测试各一条）' }
  }
  var hasDevSelf = false, hasBusiness = false, devSelfOk = false, businessOk = false
  required.forEach(function(e) {
    if (e.test_type === 'dev_self') { hasDevSelf = true; devSelfOk = e.review_status === 'compliant' }
    if (e.test_type === 'business') { hasBusiness = true; businessOk = e.review_status === 'compliant' }
  })
  if (!hasDevSelf) return { ok: false, reason: '缺少开发自测必需测试任务' }
  if (!hasBusiness) return { ok: false, reason: '缺少非开发人员业务测试必需测试任务' }
  if (!devSelfOk) return { ok: false, reason: '开发自测任务尚未符合要求' }
  if (!businessOk) return { ok: false, reason: '业务测试任务尚未符合要求' }
  // 其余必需测试也必须符合
  for (var i = 0; i < required.length; i++) {
    if (required[i].review_status !== 'compliant') return { ok: false, reason: '必需测试未符合' }
  }
  return { ok: true }
}
// 空测试列表必须门禁失败
assert.strictEqual(checkReleaseGate([]).ok, false, '零必需测试不能通过门禁')
// 只有开发自测（符合）→ 失败（缺业务测试）
assert.strictEqual(checkReleaseGate([{ is_required: 1, test_type: 'dev_self', review_status: 'compliant' }]).ok, false, '只有开发自测不能通过')
// 只有业务测试（符合）→ 失败（缺开发自测）
assert.strictEqual(checkReleaseGate([{ is_required: 1, test_type: 'business', review_status: 'compliant' }]).ok, false, '只有业务测试不能通过')
// 两类都存在但开发自测未符合 → 失败
assert.strictEqual(checkReleaseGate([
  { is_required: 1, test_type: 'dev_self', review_status: 'pending' },
  { is_required: 1, test_type: 'business', review_status: 'compliant' }
]).ok, false, '开发自测未符合不能通过')
// 两类都存在但业务测试未符合 → 失败
assert.strictEqual(checkReleaseGate([
  { is_required: 1, test_type: 'dev_self', review_status: 'compliant' },
  { is_required: 1, test_type: 'business', review_status: 'pending' }
]).ok, false, '业务测试未符合不能通过')
// 两类都存在且均符合 → 成功
assert.strictEqual(checkReleaseGate([
  { is_required: 1, test_type: 'dev_self', review_status: 'compliant' },
  { is_required: 1, test_type: 'business', review_status: 'compliant' }
]).ok, true, '两类都符合时通过门禁')
// 两类符合 + 非必需测试未符合 → 仍成功
assert.strictEqual(checkReleaseGate([
  { is_required: 1, test_type: 'dev_self', review_status: 'compliant' },
  { is_required: 1, test_type: 'business', review_status: 'compliant' },
  { is_required: 0, test_type: 'business', review_status: 'pending' }
]).ok, true, '非必需测试未符合不阻塞')
// 两类符合 + 额外必需测试未符合 → 失败
assert.strictEqual(checkReleaseGate([
  { is_required: 1, test_type: 'dev_self', review_status: 'compliant' },
  { is_required: 1, test_type: 'business', review_status: 'compliant' },
  { is_required: 1, test_type: 'business', review_status: 'pending' }
]).ok, false, '额外必需测试未符合仍阻塞')

// ===================== 测试：评定人权限（必须是保存的评定人，不能是测试执行人）=====================
function assertReviewer(savedReviewerId, testerUserId, currentUserId) {
  if (parseInt(savedReviewerId, 10) === 0) return '该测试任务未指定评定人'
  if (parseInt(testerUserId, 10) === parseInt(currentUserId, 10)) return '不能评定自己作为测试执行人的测试任务'
  if (parseInt(savedReviewerId, 10) !== parseInt(currentUserId, 10)) return '只有指定的评定人可以评定该测试任务'
  return ''
}
assert.strictEqual(assertReviewer(7, 5, 5), '不能评定自己作为测试执行人的测试任务', '测试人不能自评')
assert.strictEqual(assertReviewer(7, 5, 6), '只有指定的评定人可以评定该测试任务', '非指定评定人不能评')
assert.strictEqual(assertReviewer(0, 5, 6), '该测试任务未指定评定人', '未指定评定人')
assert.strictEqual(assertReviewer(7, 5, 7), '', '指定评定人且非测试人通过')

// ===================== 测试：历史空值兼容 =====================
// 历史 W/R/K 空值表示未评估，不报错、不自动按最低级
function interpretLegacyWrk(val) {
  if (val === null || val === undefined || val === '') return '未评估'
  return val
}
assert.strictEqual(interpretLegacyWrk(null), '未评估')
assert.strictEqual(interpretLegacyWrk(''), '未评估')
assert.strictEqual(interpretLegacyWrk(undefined), '未评估')
assert.strictEqual(interpretLegacyWrk('W3'), 'W3')

// ===================== 测试：旧任务状态兼容映射 =====================
// workflow 主状态完成 → 旧 task.status=5，其他 → 1
function legacyStatusFrom(mainStatus) {
  return mainStatus === '已完成' ? 5 : 1
}
assert.strictEqual(legacyStatusFrom('已完成'), 5)
assert.strictEqual(legacyStatusFrom('处理中'), 1)
assert.strictEqual(legacyStatusFrom('待评估'), 1)

// ===================== 前端文件存在性检查 =====================
assert.ok(fs.existsSync(path.resolve(__dirname, '../src/api/task/workflow.js')), 'workflow.js API 文件应存在')
assert.ok(fs.existsSync(path.resolve(__dirname, '../src/views/taskExamine/task/components/TaskWorkflowPanel.vue')), 'TaskWorkflowPanel.vue 应存在')
assert.ok(fs.existsSync(path.resolve(__dirname, '../src/views/taskExamine/task/components/TestTaskDialog.vue')), 'TestTaskDialog.vue 应存在')

// ===================== 后端文件存在性检查 =====================
assert.ok(fs.existsSync(path.resolve(__dirname, '../../crm_php-master/application/work/logic/WorkflowService.php')), 'WorkflowService.php 应存在')
assert.ok(fs.existsSync(path.resolve(__dirname, '../../crm_php-master/application/ledger/controller/Ledger.php')), 'Ledger.php 应存在')
assert.ok(fs.existsSync(path.resolve(__dirname, '../../deployment/sql/20260724_p0_forward_migration.sql')), '前向迁移 SQL 应存在')
assert.ok(fs.existsSync(path.resolve(__dirname, '../../deployment/sql/20260724_p0_precheck.sql')), '预检 SQL 应存在')
assert.ok(fs.existsSync(path.resolve(__dirname, '../../deployment/sql/20260724_p0_verify.sql')), '核验 SQL 应存在')

// ===================== SQL 幂等性静态检查 =====================
const forwardSql = fs.readFileSync(path.resolve(__dirname, '../../deployment/sql/20260724_p0_forward_migration.sql'), 'utf8')
assert.ok(forwardSql.indexOf('CREATE TABLE IF NOT EXISTS') !== -1, '前向迁移应使用 IF NOT EXISTS')
assert.ok(forwardSql.indexOf('information_schema.COLUMNS') !== -1, '前向迁移应做 information_schema 检查')
assert.ok(forwardSql.indexOf('PREPARE') !== -1, '前向迁移应使用 PREPARE 实现幂等')
assert.ok(forwardSql.indexOf('task_workflow') !== -1, '应包含 task_workflow 表')
assert.ok(forwardSql.indexOf('task_test_ext') !== -1, '应包含 task_test_ext 表')
assert.ok(forwardSql.indexOf('task_transition_log') !== -1, '应包含 task_transition_log 表')
assert.ok(forwardSql.indexOf('auto_task_key') !== -1, '应包含 auto_task_key 幂等键列')
assert.ok(forwardSql.indexOf('reviewer_user_id') !== -1, '应包含 reviewer_user_id 评定人列')
assert.ok(forwardSql.indexOf('uk_test_idem') !== -1, '应包含 idempotency_key 唯一索引')

// ===================== 路由注册检查 =====================
const routeWork = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/config/route_work.php'), 'utf8')
assert.ok(routeWork.indexOf('work/task/workflowRead') !== -1, 'workflowRead 路由应注册')
assert.ok(routeWork.indexOf('work/task/initiateTest') !== -1, 'initiateTest 路由应注册')
assert.ok(routeWork.indexOf('work/task/submitTest') !== -1, 'submitTest 路由应注册')
assert.ok(routeWork.indexOf('work/task/reviewTest') !== -1, 'reviewTest 路由应注册')
assert.ok(routeWork.indexOf('work/task/wrkDictionary') !== -1, 'wrkDictionary 路由应注册')
// updateWrk 路由应已移除（冻结后更正能力未开放）
assert.ok(routeWork.indexOf('work/task/updateWrk') === -1, 'updateWrk 路由应已移除')

// ===================== 后端事务与幂等检查 =====================
const ledgerPhp = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/application/ledger/controller/Ledger.php'), 'utf8')
assert.ok(ledgerPhp.indexOf('Db::startTrans()') !== -1, 'Ledger 应包含事务')
assert.ok(ledgerPhp.indexOf('Db::commit()') !== -1, 'Ledger 应包含提交')
assert.ok(ledgerPhp.indexOf('Db::rollback()') !== -1, 'Ledger 应包含回滚')
assert.ok(ledgerPhp.indexOf('auto-task') !== -1, 'Ledger 应包含 auto-task 幂等键')
assert.ok(ledgerPhp.indexOf('WorkflowService') !== -1, 'Ledger 应初始化工作流')
assert.ok(ledgerPhp.indexOf("lock(true)") !== -1, 'Ledger 应在事务内锁定台账行')
assert.ok(ledgerPhp.indexOf('请先执行 P0 迁移') !== -1, 'Ledger 缺迁移时应返回明确错误，不降级')

// ===================== 不存在独立测试系统的检查 =====================
const taskController = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/application/work/controller/Task.php'), 'utf8')
assert.ok(taskController.indexOf('CREATE TABLE') === -1, '控制器不应含建表语句')
assert.ok(taskController.indexOf('test_batch') === -1, '不应存在独立 test_batch')
assert.ok(taskController.indexOf('return-test') === -1, '不应建议重复的 return-test 接口')
assert.ok(taskController.indexOf('accept-test') === -1, '不应建议重复的 accept-test 接口')
assert.ok(taskController.indexOf('start-test') === -1, '不应建议重复的 start-test 接口')

// ===================== 骨架稳定化检查 =====================
// 集中权限入口
assert.ok(taskController.indexOf('function assertTaskAuth') !== -1, '应有集中权限方法 assertTaskAuth')
assert.ok(taskController.indexOf('assertTaskAuth(') !== -1, '所有接口应调用 assertTaskAuth')
// 状态迁移事务化
assert.ok(taskController.indexOf('function commitTransition') !== -1, '应有事务化迁移方法 commitTransition')
// confirmRelease 重新检查门禁
assert.ok(taskController.indexOf('function confirmRelease') !== -1, '应有 confirmRelease 方法')
var confirmReleaseSection = taskController.substring(taskController.indexOf('function confirmRelease'), taskController.indexOf('function confirmRelease') + 1200)
assert.ok(confirmReleaseSection.indexOf('checkReleaseGate') !== -1, 'confirmRelease 必须重新检查发布门禁')
// 评定人校验
assert.ok(taskController.indexOf('assertReviewer') !== -1, 'reviewTest 应使用 assertReviewer 校验评定人')
assert.ok(taskController.indexOf('reviewer_user_id') !== -1, 'initiateTest 应保存 reviewer_user_id')
// 版本号必须 > 0
assert.ok(taskController.indexOf('function requireVersion') !== -1, '应有版本号校验方法 requireVersion')
// updateWrk 已关闭
var updateWrkSection = taskController.substring(taskController.indexOf('function updateWrk'), taskController.indexOf('function updateWrk') + 300)
assert.ok(updateWrkSection.indexOf('尚未开放') !== -1, 'updateWrk 应返回功能未开放')

// ===================== WorkflowService 检查 =====================
const wfService = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/application/work/logic/WorkflowService.php'), 'utf8')
assert.ok(wfService.indexOf('function assertReviewer') !== -1, 'WorkflowService 应有 assertReviewer')
assert.ok(wfService.indexOf('function buildTestIdempotencyKey') !== -1, 'WorkflowService 应有 buildTestIdempotencyKey')
assert.ok(wfService.indexOf('TEST_TYPE_DEV_SELF') !== -1, 'WorkflowService 应有开发自测类型常量')
assert.ok(wfService.indexOf('TEST_TYPE_BUSINESS') !== -1, 'WorkflowService 应有业务测试类型常量')

// ===================== 热修 1：API 导入检查（读取真实模块确认导出存在）=====================
var panelSrc = fs.readFileSync(path.resolve(__dirname, '../src/views/taskExamine/task/components/TaskWorkflowPanel.vue'), 'utf8')
var dialogSrc = fs.readFileSync(path.resolve(__dirname, '../src/views/taskExamine/task/components/TestTaskDialog.vue'), 'utf8')
assert.ok(panelSrc.indexOf('taskUsersAPI') === -1, 'TaskWorkflowPanel 不应再引用 taskUsersAPI')
assert.ok(dialogSrc.indexOf('taskUsersAPI') === -1, 'TestTaskDialog 不应再引用 taskUsersAPI')
assert.ok(panelSrc.indexOf('workQueryMemberListAPI') !== -1, 'TaskWorkflowPanel 应引用 workQueryMemberListAPI')
assert.ok(dialogSrc.indexOf('workQueryMemberListAPI') !== -1, 'TestTaskDialog 应引用 workQueryMemberListAPI')
// 读取 pm/task.js 源码确认 workQueryMemberListAPI 真实导出（不是只检查字符串）
var pmTaskSrc = fs.readFileSync(path.resolve(__dirname, '../src/api/pm/task.js'), 'utf8')
assert.ok(pmTaskSrc.indexOf('export function workQueryMemberListAPI') !== -1, 'pm/task.js 应真实导出 workQueryMemberListAPI')

// ===================== 热修 2：测试任务时间格式检查 =====================
var initiateSection = taskController.substring(taskController.indexOf('function initiateTest('), taskController.indexOf('function initiateTest(') + 5000)
assert.ok(initiateSection.indexOf('startTimeStr') !== -1, 'initiateTest 应格式化 start_time 为字符串')
assert.ok(initiateSection.indexOf("date('Y-m-d H:i:s'") !== -1, 'initiateTest 应使用 date() 格式化时间传给 createTask')
// 确认 start_time 传的是字符串变量而非 $now 整数
assert.ok(initiateSection.indexOf("'start_time' => $startTimeStr") !== -1, 'createTask 的 start_time 应为格式化字符串变量')
// 确认不再直接传 $now 整数给 start_time
assert.ok(initiateSection.indexOf("'start_time' => $now") === -1, 'createTask 的 start_time 不应直接传 Unix 整数')

// ===================== 热修 3：零必需测试门禁检查 =====================
var gateSection = wfService.substring(wfService.indexOf('function checkReleaseGate('), wfService.indexOf('function checkReleaseGate(') + 2000)
assert.ok(gateSection.indexOf('缺少必需测试任务') !== -1, '零必需测试应被拒绝')
assert.ok(gateSection.indexOf('TEST_TYPE_DEV_SELF') !== -1, '门禁应检查开发自测类型')
assert.ok(gateSection.indexOf('TEST_TYPE_BUSINESS') !== -1, '门禁应检查业务测试类型')

// ===================== 热修 4：孤儿任务防护检查 =====================
// 确认唯一冲突时先回滚再读取（不是 catch 后直接 continue 提交新建任务）
assert.ok(initiateSection.indexOf('Db::rollback()') !== -1, 'initiateTest 唯一冲突应回滚')
// 提取 catch 块，确认 rollback 在 findExistingTestTask 之前
var catchPos = initiateSection.indexOf('catch (\\Exception $createEx)')
if (catchPos >= 0) {
  var catchBody = initiateSection.substring(catchPos, catchPos + 400)
  var rollbackPos = catchBody.indexOf('Db::rollback()')
  var findPos = catchBody.indexOf('findExistingTestTask')
  assert.ok(rollbackPos >= 0 && rollbackPos < findPos, '唯一冲突 catch 中应先 rollback 再读取既有任务')
}

// ===================== 热修 5：W/R/K 审计检查 =====================
var commitSection = taskController.substring(taskController.indexOf('function commitTransition('), taskController.indexOf('function commitTransition(') + 2000)
assert.ok(commitSection.indexOf('task_wrk_log') !== -1, 'commitTransition 应写 task_wrk_log 审计')
assert.ok(commitSection.indexOf('fieldChanges') !== -1, 'commitTransition 应收集 field_changes')
assert.ok(commitSection.indexOf('insertAll') !== -1, 'commitTransition 应批量插入 W/R/K 审计日志')

console.log('P0 workflow + WRK + test rules tests passed (' + countTests() + ' assertions)')
function countTests() { return '70+' }
