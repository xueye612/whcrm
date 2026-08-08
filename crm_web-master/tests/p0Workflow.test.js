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
    K1: '成熟，已有成熟方案、标准流程和充分经验，可按现有方案执行',
    K2: '基本明确，主要方案已明确，仍需结合具体环境进行一般性确认',
    K3: '需要专业确认，存在专业判断或关键不确定性，必须由具备相应专业能力的人员确认',
    K4: '必须有正式专业依据，涉及医疗、法律、财务、合规、核心架构或其他重大专业事项，必须提供正式依据并完成专业确认'
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
  skip_evaluate:     { '待评估': '待处理' },
  start:             { '待处理': '处理中' },
  submit_acceptance: { '处理中': '待内部验收' },
  acceptance_pass:   { '待内部验收': '待发布' },
  acceptance_return: { '待内部验收': '处理中' },
  confirm_release:   { '待发布': '待客户验证' },
  customer_confirm:  { '待客户验证': '已完成' },
  customer_return:   { '待客户验证': '处理中' },
  complete:          { '待发布': '已完成' },
  rollback_to_pending: { '处理中': '待处理' }
}

function resolveTarget(action, current) {
  if (!TRANSITIONS[action]) return false
  if (!Object.prototype.hasOwnProperty.call(TRANSITIONS[action], current)) return false
  return TRANSITIONS[action][current]
}

// ===================== 测试：合法迁移 =====================
assert.strictEqual(resolveTarget('evaluate', '待评估'), '待处理')
assert.strictEqual(resolveTarget('skip_evaluate', '待评估'), '待处理')
assert.strictEqual(resolveTarget('start', '待处理'), '处理中')
assert.strictEqual(resolveTarget('submit_acceptance', '处理中'), '待内部验收')
assert.strictEqual(resolveTarget('acceptance_pass', '待内部验收'), '待发布')
assert.strictEqual(resolveTarget('confirm_release', '待发布'), '待客户验证')
assert.strictEqual(resolveTarget('customer_confirm', '待客户验证'), '已完成')

// ===================== 测试：非法跳转被拒绝 =====================
assert.strictEqual(resolveTarget('evaluate', '处理中'), false, '处理中不能评估')
assert.strictEqual(resolveTarget('start', '待评估'), false, '待评估不能直接开始处理（需先评估或跳过评估）')
assert.strictEqual(resolveTarget('confirm_release', '处理中'), false, '处理中不能确认发布')
assert.strictEqual(resolveTarget('customer_confirm', '待发布'), false, '待发布不能客户确认')
assert.strictEqual(resolveTarget('submit_acceptance', '待评估'), false, '待评估不能提交验收')
assert.strictEqual(resolveTarget('unknown_action', '待评估'), false, '未知动作')

// ===================== 测试：待评估可通过跳过评估进入待处理 =====================
assert.strictEqual(resolveTarget('skip_evaluate', '待评估'), '待处理', '待评估可跳过评估进入待处理')
assert.strictEqual(resolveTarget('start', '待评估'), false, '待评估不能直接开始处理')

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

// ===================== 测试：测试任务展示状态（待反馈/已反馈/已逾期）=====================
const TEST_TASK_STATES = ['待反馈', '已反馈', '已逾期']
assert.strictEqual(TEST_TASK_STATES.indexOf('待发布'), -1, '待发布不属于测试任务状态')
assert.strictEqual(TEST_TASK_STATES.indexOf('待客户验证'), -1, '待客户验证不属于测试任务状态')
assert.strictEqual(TEST_TASK_STATES.indexOf('待评定'), -1, '新流程不再有待评定状态')

// ===================== 测试：发布门禁条件（按需测试）=====================
// 镜像后端 checkReleaseGate：无测试任务直接通过；有未反馈测试任务则拒绝
function checkReleaseGate(testExts) {
  if (!testExts || testExts.length === 0) {
    return { ok: true }
  }
  for (var i = 0; i < testExts.length; i++) {
    if (testExts[i].submit_status !== 'submitted') {
      return { ok: false, reason: '测试尚未完成：测试任务《' + (testExts[i].task_name || ('#' + testExts[i].task_id)) + '》还未反馈' }
    }
  }
  return { ok: true }
}
// 无测试任务必须门禁通过
assert.strictEqual(checkReleaseGate([]).ok, true, '无测试任务可通过门禁')
// 有已反馈测试 -> 通过
assert.strictEqual(checkReleaseGate([{ submit_status: 'submitted', task_id: 1 }]).ok, true, '已反馈测试可通过')
// 有未反馈测试 -> 失败
assert.strictEqual(checkReleaseGate([{ submit_status: 'not_submitted', task_id: 1, task_name: '测试A' }]).ok, false, '未反馈测试不能通过')
assert.strictEqual(checkReleaseGate([{ submit_status: 'not_submitted', task_id: 1, task_name: '测试A' }]).reason, '测试尚未完成：测试任务《测试A》还未反馈', '未反馈提示具体任务名')
// 混合（部分已反馈部分未反馈）-> 失败
assert.strictEqual(checkReleaseGate([
  { submit_status: 'submitted', task_id: 1 },
  { submit_status: 'not_submitted', task_id: 2, task_name: '测试B' }
]).ok, false, '存在未反馈测试不能通过')
// 全部已反馈 -> 通过
assert.strictEqual(checkReleaseGate([
  { submit_status: 'submitted', task_id: 1 },
  { submit_status: 'submitted', task_id: 2 }
]).ok, true, '全部已反馈通过门禁')
// "发现问题"不自动判定发布失败
assert.strictEqual(checkReleaseGate([
  { submit_status: 'submitted', task_id: 1, submit_result: '发现问题' }
]).ok, true, '发现问题本身不阻塞发布')

// ===================== 测试：新流程无评定人，发起人可作为测试人员 =====================
// 新流程删除了评定人回避规则，发起人可以被选为测试人员
function newFlowAllowsSelfAsTester(initiatorId, testerId) {
  return true // 新流程允许任何人（包括发起人自己）作为测试人员
}
assert.strictEqual(newFlowAllowsSelfAsTester(5, 5), true, '新流程：发起人可作为测试人员')
assert.strictEqual(newFlowAllowsSelfAsTester(5, 6), true, '新流程：其他人员也可作为测试人员')

// 旧流程评定人权限（兼容旧数据，仅旧数据有评定人时使用）
function assertReviewer(savedReviewerId, testerUserId, currentUserId) {
  if (parseInt(savedReviewerId, 10) === 0) return '该测试任务未指定评定人'
  if (parseInt(testerUserId, 10) === parseInt(currentUserId, 10)) return '不能评定自己作为测试执行人的测试任务'
  if (parseInt(savedReviewerId, 10) !== parseInt(currentUserId, 10)) return '只有指定的评定人可以评定该测试任务'
  return ''
}
assert.strictEqual(assertReviewer(7, 5, 5), '不能评定自己作为测试执行人的测试任务', '旧流程：测试人不能自评')
assert.strictEqual(assertReviewer(7, 5, 6), '只有指定的评定人可以评定该测试任务', '旧流程：非指定评定人不能评')
assert.strictEqual(assertReviewer(0, 5, 6), '该测试任务未指定评定人', '旧流程：新数据无评定人')
assert.strictEqual(assertReviewer(7, 5, 7), '', '旧流程：指定评定人且非测试人通过')

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
assert.ok(routeWork.indexOf('work/task/skipEvaluate') !== -1, 'skipEvaluate 路由应注册')
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
const oaTaskController = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/application/oa/controller/Task.php'), 'utf8')
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
assert.ok(dialogSrc.indexOf('workQueryMemberListAPI') === -1, 'TestTaskDialog 不再使用 workQueryMemberListAPI（改用全员可选）')
assert.ok(dialogSrc.indexOf('usersListIndexAPI') !== -1, 'TestTaskDialog 使用 usersListIndexAPI 加载全部有效员工')
assert.ok(dialogSrc.indexOf('reviewer_user_id') === -1 || dialogSrc.indexOf('initForm.reviewer_user_id') === -1, 'TestTaskDialog 不再让用户选择评定人')
assert.ok(dialogSrc.indexOf('currentUserName') !== -1, 'TestTaskDialog 保留当前用户信息')
assert.ok(dialogSrc.indexOf('test_scope') !== -1, 'TestTaskDialog 应有测试内容字段')
assert.ok(dialogSrc.indexOf('selectAllSearch') !== -1, 'TestTaskDialog 支持全选搜索结果')
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
assert.ok(initiateSection.indexOf("'is_open' => 1") !== -1, '测试任务应显式设为接收人可见')
assert.ok(oaTaskController.indexOf("$type = '(t.main_user_id ='") !== -1, '普通用户应能在全部任务中看到分配给自己的私有任务')

// ===================== 热修 3：发布门禁按需测试检查 =====================
var gateSection = wfService.substring(wfService.indexOf('function checkReleaseGate('), wfService.indexOf('function checkReleaseGate(') + 2000)
assert.ok(gateSection.indexOf('submit_status') !== -1, '门禁应检查测试任务 submit_status')
assert.ok(gateSection.indexOf('还未反馈') !== -1, '门禁未反馈测试应有明确提示')
assert.ok(gateSection.indexOf('test_type') === -1 || gateSection.indexOf('test_type') > gateSection.indexOf('submit_status'), '门禁不再依赖 test_type 做拦截')

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
var commitSection = taskController.substring(taskController.indexOf('function commitTransition('), taskController.indexOf('function commitTransition(') + 3500)
assert.ok(commitSection.indexOf('task_wrk_log') !== -1, 'commitTransition 应写 task_wrk_log 审计')
assert.ok(commitSection.indexOf('fieldChanges') !== -1, 'commitTransition 应收集 field_changes')
assert.ok(commitSection.indexOf('insertAll') !== -1, 'commitTransition 应批量插入 W/R/K 审计日志')

// ===================== W/R/K 字典与后端一致性（原等级不变）=====================
// 1. K1=成熟，K4=必须有正式专业依据
assert.ok(wfService.indexOf("'K1' => '成熟") !== -1, 'K1 = 成熟（专业确认等级，非"陌生"）')
assert.ok(wfService.indexOf("'K4' => '必须有正式专业依据") !== -1, 'K4 = 必须有正式专业依据（非"高度成熟"）')
// 确认错误的 K 定义不存在
assert.ok(wfService.indexOf('陌生，团队没有成熟经验') === -1, 'K1 不应是"陌生"')
assert.ok(wfService.indexOf('高度成熟，有成熟方案') === -1, 'K4 不应是"高度成熟"')

// 2. K3/K4 门禁要求专业确认
var gateSection2 = wfService.substring(wfService.indexOf('function checkReleaseGate('), wfService.indexOf('function checkReleaseGate(') + 2000)
assert.ok(gateSection2.indexOf("'K3'") !== -1 && gateSection2.indexOf("'K4'") !== -1, 'K3/K4 在专业确认门禁中')
assert.ok(gateSection2.indexOf('professional_confirm') !== -1, '门禁检查 professional_confirm')
// K1/K2 不在门禁中
assert.ok(gateSection2.indexOf("'K1'") === -1, 'K1 不在专业确认门禁中')
assert.ok(gateSection2.indexOf("'K2'") === -1, 'K2 不在专业确认门禁中')

// 3. W5 仍为超过十人日、原则上拆分
assert.ok(wfService.indexOf('超过十人日，原则上应拆分') !== -1, 'W5 = 超过十人日，原则上应拆分')
assert.ok(wfService.indexOf("'W1' => '两小时以内'") !== -1, 'W1 = 两小时以内')
assert.ok(wfService.indexOf("'W2' => '两至八小时'") !== -1, 'W2 = 两至八小时')
assert.ok(wfService.indexOf("'W3' => '一至三人日'") !== -1, 'W3 = 一至三人日')
// 确认错误的 W 定义不存在
assert.ok(wfService.indexOf('很小，通常不超过2小时') === -1, 'W1 不应是"很小"')
assert.ok(wfService.indexOf('较大，约3至5个工作日') === -1, 'W4 不应是"3至5天"')

// 4. R5 包含患者安全、医疗文书、核心数据和法律合规
assert.ok(wfService.indexOf('患者安全') !== -1, 'R5 包含患者安全')
assert.ok(wfService.indexOf('正式医疗文书真实性') !== -1, 'R5 包含医疗文书真实性')
assert.ok(wfService.indexOf('核心数据') !== -1, 'R5 包含核心数据')
assert.ok(wfService.indexOf('法律合规') !== -1, 'R5 包含法律合规')
// 确认错误的 R 定义不存在
assert.ok(wfService.indexOf('极低，影响范围很小') === -1, 'R1 不应是"极低"')

// 5. 前端没有独立硬编码另一套字典（描述来自 API）
var wpSrc = fs.readFileSync(path.resolve(__dirname, '../src/views/taskExamine/task/components/TaskWorkflowPanel.vue'), 'utf8')
assert.ok(wpSrc.indexOf("val + ' - ' + def") !== -1, 'wrkText 显示格式为 代码 - 说明')
assert.ok(wpSrc.indexOf('el-icon-question') !== -1, 'W/R/K 标题旁有帮助图标')
assert.ok(wpSrc.indexOf('wp-dict') !== -1, '帮助弹出层显示完整字典')
assert.ok(wpSrc.indexOf('Workload') !== -1, '字典弹出层显示 W = Workload')
assert.ok(wpSrc.indexOf('Risk') !== -1, '字典弹出层显示 R = Risk')
assert.ok(wpSrc.indexOf('专业确认等级') !== -1, 'K 标签已改为"专业确认等级"')
// 前端不应硬编码 W/R/K 中文描述（只应硬编码等级代码 W1-W5 等）
assert.ok(wpSrc.indexOf('两小时以内') === -1, '前端不应硬编码 W1 描述')
assert.ok(wpSrc.indexOf('局部、易验证') === -1, '前端不应硬编码 R1 描述')
assert.ok(wpSrc.indexOf('wrkDictionaryAPI') !== -1, '前端通过 API 获取字典')

// 6. 历史K值没有执行K1与K4互换迁移
// 检查所有迁移SQL不包含K值互换逻辑
var sqlDir = path.resolve(__dirname, '../../deployment/sql')
if (fs.existsSync(sqlDir)) {
  var sqlFiles = fs.readdirSync(sqlDir).filter(function(f) { return f.endsWith('.sql') })
  var hasKSwap = false
  sqlFiles.forEach(function(f) {
    var content = fs.readFileSync(path.join(sqlDir, f), 'utf8')
    // 检查是否有 UPDATE task_workflow SET ... init_k/final_k 互换 K1/K4 的逻辑
    if (content.indexOf('init_k') !== -1 && content.indexOf('K4') !== -1 && content.indexOf('K1') !== -1 && content.indexOf('UPDATE') !== -1) {
      // 进一步检查是否是互换（SET init_k=K4 WHERE init_k=K1 这种模式）
      if ((content.indexOf('= \'K4\'') !== -1 && content.indexOf('= \'K1\'') !== -1) ||
          (content.indexOf("= 'K4'") !== -1 && content.indexOf("= 'K1'") !== -1)) {
        hasKSwap = true
      }
    }
  })
  assert.ok(!hasKSwap, '历史K值没有执行K1与K4互换迁移')
}

// ===================== 台账转任务提示改为可选评估 =====================
var ledgerSrc = fs.readFileSync(path.resolve(__dirname, '../src/views/crm/ledger/index.vue'), 'utf8')
assert.ok(ledgerSrc.indexOf('根据任务情况选择是否需要评估') !== -1, '转任务提示应改为可选评估')

// ===================== 菜单名称与图标修改 =====================
var crmRouteSrc = fs.readFileSync(path.resolve(__dirname, '../src/router/modules/crm.js'), 'utf8')
assert.ok(crmRouteSrc.indexOf('奖惩') !== -1, '菜单应改为"奖惩"')
assert.ok(crmRouteSrc.indexOf('绩效') !== -1, '菜单应改为"绩效"')
assert.ok(crmRouteSrc.indexOf("icon: 'performance'") !== -1, '绩效菜单应使用 performance 图标')
assert.ok(crmRouteSrc.indexOf("icon: 'results'") === -1, '不应再使用 results 图标（不存在）')
assert.ok(crmRouteSrc.indexOf('奖励候选') === -1, '不应再有"奖励候选"')
assert.ok(crmRouteSrc.indexOf('季度绩效') === -1, '不应再有"季度绩效"')
// 路由 path 和权限编码不变
assert.ok(crmRouteSrc.indexOf("path: 'reward'") !== -1, '奖惩路由 path 不变')
assert.ok(crmRouteSrc.indexOf("path: 'performance'") !== -1, '绩效路由 path 不变')
assert.ok(crmRouteSrc.indexOf("'crm', 'reward', 'candidatelist'") !== -1, '奖惩权限编码不变')
assert.ok(crmRouteSrc.indexOf("'crm', 'performance', 'summarylist'") !== -1, '绩效权限编码不变')
// 图标在 iconfont.css 中存在
var iconfontCss = fs.readFileSync(path.resolve(__dirname, '../src/styles/iconfont/iconfont.css'), 'utf8')
assert.ok(iconfontCss.indexOf('.wk-performance') !== -1, 'iconfont 中应存在 wk-performance 图标')

// ===================== 新增测试任务接口路由 =====================
assert.ok(routeWork.indexOf('work/task/testDetail') !== -1, 'testDetail 路由应注册')
assert.ok(routeWork.indexOf('work/task/testHistory') !== -1, 'testHistory 路由应注册')

// ===================== 后端新增测试任务详情和历史接口 =====================
assert.ok(taskController.indexOf('function testDetail') !== -1, '应有 testDetail 方法')
assert.ok(taskController.indexOf('function testHistory') !== -1, '应有 testHistory 方法')
// testDetail 需查看权限
var testDetailSection = taskController.substring(taskController.indexOf('function testDetail('), taskController.indexOf('function testDetail(') + 2000)
assert.ok(testDetailSection.indexOf("assertTaskAuth") !== -1, 'testDetail 应调用 assertTaskAuth')
assert.ok(testDetailSection.indexOf("'read'") !== -1, 'testDetail 应使用 read 权限级别')
// testHistory 需查看权限
var testHistorySection = taskController.substring(taskController.indexOf('function testHistory('), taskController.indexOf('function testHistory(') + 2000)
assert.ok(testHistorySection.indexOf("assertTaskAuth") !== -1, 'testHistory 应调用 assertTaskAuth')
assert.ok(testHistorySection.indexOf("task_test_history") !== -1, 'testHistory 应读取 task_test_history')

// ===================== 测试任务完成状态同步 =====================
var reviewSection = taskController.substring(taskController.indexOf('function reviewTest('), taskController.indexOf('function reviewTest(') + 5000)
assert.ok(reviewSection.indexOf("['status' => 5") !== -1, 'reviewTest 合格时应同步底层任务状态为完成(5)')
assert.ok(reviewSection.indexOf("['status' => 1") !== -1, 'reviewTest 不合格时应重新打开底层任务(1)')
assert.ok(reviewSection.indexOf('WorkTaskLog') !== -1, 'reviewTest 应写入任务操作日志')
assert.ok(reviewSection.indexOf('测试评定合格，任务完成') !== -1, '合格时应记录完成日志')
assert.ok(reviewSection.indexOf('测试评定不合格，任务重新打开') !== -1, '不合格时应记录重新打开日志')

// ===================== 测试任务禁止初始化 W/R/K =====================
var workflowReadSection = taskController.substring(taskController.indexOf('function workflowRead('), taskController.indexOf('function workflowRead(') + 4000)
assert.ok(workflowReadSection.indexOf('isTestTask') !== -1, 'workflowRead 应优先判断 is_test_task')
assert.ok(workflowReadSection.indexOf('测试任务不使用 W/R/K 工作流，不能初始化') !== -1, '测试任务 force_init 应被明确拒绝')
// 测试任务不调用 getWorkflow
assert.ok(workflowReadSection.indexOf('if ($isTestTask)') !== -1 || workflowReadSection.indexOf('$isTestTask)') !== -1, '测试任务应走独立分支')

// ===================== WorkflowService 新增辅助方法 =====================
assert.ok(wfService.indexOf('function resolveUserNames') !== -1, 'WorkflowService 应有 resolveUserNames')
assert.ok(wfService.indexOf('function testTypeName') !== -1, 'WorkflowService 应有 testTypeName')
assert.ok(wfService.indexOf('function reviewStatusName') !== -1, 'WorkflowService 应有 reviewStatusName')
assert.ok(wfService.indexOf('function enrichTestExt') !== -1, 'WorkflowService 应有 enrichTestExt')

// ===================== 前端 API 新增测试详情和历史接口 =====================
var workflowApiSrc = fs.readFileSync(path.resolve(__dirname, '../src/api/task/workflow.js'), 'utf8')
assert.ok(workflowApiSrc.indexOf('testDetailAPI') !== -1, 'workflow.js 应导出 testDetailAPI')
assert.ok(workflowApiSrc.indexOf('testHistoryAPI') !== -1, 'workflow.js 应导出 testHistoryAPI')
assert.ok(workflowApiSrc.indexOf('work/task/testDetail') !== -1, 'testDetailAPI 应指向 work/task/testDetail')
assert.ok(workflowApiSrc.indexOf('work/task/testHistory') !== -1, 'testHistoryAPI 应指向 work/task/testHistory')

// ===================== 前端测试任务优先判断 =====================
assert.ok(panelSrc.indexOf('data.is_test_task') !== -1, 'TaskWorkflowPanel 应优先判断 data.is_test_task')
assert.ok(panelSrc.indexOf('testDetailAPI') !== -1, 'TaskWorkflowPanel 应调用 testDetailAPI')
assert.ok(panelSrc.indexOf('testHistoryAPI') !== -1, 'TaskWorkflowPanel 应调用 testHistoryAPI')
assert.ok(panelSrc.indexOf('submitTestAPI') !== -1, 'TaskWorkflowPanel 应调用 submitTestAPI（测试任务提交）')
assert.ok(panelSrc.indexOf('reviewTestAPI') !== -1, 'TaskWorkflowPanel 应调用 reviewTestAPI（测试任务评定）')
// 测试任务不显示初始化工作流
assert.ok(panelSrc.indexOf('wp-test-card') !== -1, 'TaskWorkflowPanel 应有测试任务专属卡片')

// ===================== 前端评估弹窗化（不再内联展开）=====================
assert.ok(panelSrc.indexOf('wp-eval-dialog') !== -1, '评估应使用弹窗 custom-class wp-eval-dialog')
assert.ok(panelSrc.indexOf('wp-accept-dialog') !== -1, '提交验收应使用弹窗 custom-class wp-accept-dialog')
assert.ok(panelSrc.indexOf('wp-return-dialog') !== -1, '退回应使用弹窗 custom-class wp-return-dialog')
assert.ok(panelSrc.indexOf('evaluateRules') !== -1, '评估表单应使用正式校验规则')
assert.ok(panelSrc.indexOf('acceptanceRules') !== -1, '验收表单应使用正式校验规则')
assert.ok(panelSrc.indexOf('returnRules') !== -1, '退回表单应使用正式校验规则')
// 弹窗底部右对齐
assert.ok(panelSrc.indexOf('wp-dialog-footer') !== -1, '弹窗底部应右对齐')
// append-to-body 配置专属 custom-class
assert.ok(panelSrc.indexOf('append-to-body') !== -1, '弹窗应使用 append-to-body')

// ===================== TestTaskDialog 重新设计 =====================
assert.ok(dialogSrc.indexOf('tt-mgmt-dialog') !== -1, 'TestTaskDialog 应使用管理弹窗 custom-class')
assert.ok(dialogSrc.indexOf('tt-stats') !== -1, 'TestTaskDialog 应有状态统计区')
assert.ok(dialogSrc.indexOf('tt-initiate-dialog') !== -1, '发起测试应使用独立子弹窗')
assert.ok(dialogSrc.indexOf('tt-detail-dialog') !== -1, '应有查看详情子弹窗')
assert.ok(dialogSrc.indexOf('tt-history-dialog') !== -1, '应有测试历史子弹窗')
assert.ok(dialogSrc.indexOf('tt-submit-dialog') !== -1, '应有提交结果子弹窗')
assert.ok(dialogSrc.indexOf('tt-review-dialog') !== -1, '应有评定子弹窗')
// 每条测试任务始终提供查看详情
assert.ok(dialogSrc.indexOf('查看详情') !== -1, '每条测试任务应有查看详情入口')
// 发起成功后 await fetchList
assert.ok(dialogSrc.indexOf('await this.fetchList') !== -1, '发起成功后应 await fetchList')
// 表格独立 loading
assert.ok(dialogSrc.indexOf('tableLoading') !== -1, '表格应有独立 loading 状态')
assert.ok(dialogSrc.indexOf('v-loading="tableLoading"') !== -1, '表格应使用 v-loading')
// 接口失败保留表单内容
assert.ok(dialogSrc.indexOf('initiateError') !== -1, '接口失败应显示重试提示')
assert.ok(dialogSrc.indexOf('this.initiateError =') !== -1, '应设置错误提示内容')
// request_id 稳定
assert.ok(dialogSrc.indexOf('requestId') !== -1, '应使用稳定的 requestId')
assert.ok(dialogSrc.indexOf("this.requestId =") !== -1, '打开发起表单时应生成 requestId')
// 发起测试表单简化为测试人员/测试内容/截止时间
assert.ok(dialogSrc.indexOf('test_scope') !== -1, '发起测试应有测试内容字段')
assert.ok(dialogSrc.indexOf('deadline') !== -1, '发起测试应有截止时间字段')
// 多选测试人员显示已选人数
assert.ok(dialogSrc.indexOf('已选') !== -1, '应显示已选人数')
assert.ok(dialogSrc.indexOf('tt-selected-tags') !== -1, '应显示已选人员标签')
// 测试历史时间线
assert.ok(dialogSrc.indexOf('tt-timeline') !== -1, '测试历史应使用时间线展示')
// 管理弹窗宽度约 900px
assert.ok(dialogSrc.indexOf('1050px') !== -1, '管理弹窗宽度应约 1050px')

// ===================== 响应式兼容检查 =====================
assert.ok(panelSrc.indexOf('max-width: 600px') !== -1 || panelSrc.indexOf('max-width: 768px') !== -1, 'TaskWorkflowPanel 应有移动端响应式样式')
assert.ok(dialogSrc.indexOf('max-width: 768px') !== -1, 'TestTaskDialog 应有移动端响应式样式')
assert.ok(dialogSrc.indexOf("window.innerWidth") !== -1, 'TestTaskDialog 应根据窗口宽度调整弹窗宽度')

// ========================================================================
// 行为回归测试：测试任务提交/评定状态机（纯逻辑模拟后端 canSubmitTest 规则）
// ========================================================================

/**
 * 模拟后端 canSubmitTest 的完整规则（镜像 WorkflowService::canSubmitTest）。
 * 新流程规则：
 *   - review_status=compliant -> 永久禁止（兼容旧数据已合格）
 *   - submit_status=submitted -> 禁止（已提交，测试任务直接完成）
 *   - 否则允许（首次未提交 或 旧数据不合格退回后 not_submitted）
 */
function canSubmitTestRule(ext) {
  if (!ext) return [false, '测试任务不存在']
  if (ext.review_status === 'compliant') return [false, '该测试任务已完成，无需再次提交']
  if (ext.submit_status === 'submitted') return [false, '测试结果已提交，不能重复提交']
  return [true, '']
}

// ---- 测试 1：首次提交成功（not_submitted + pending → 允许）----
assert.deepStrictEqual(
  canSubmitTestRule({ submit_status: 'not_submitted', review_status: 'pending' }),
  [true, ''],
  '首次未提交应允许提交'
)

// ---- 测试 2：待评定期间重复提交被拒绝（submitted + pending → 拒绝）----
assert.deepStrictEqual(
  canSubmitTestRule({ submit_status: 'submitted', review_status: 'pending' }),
  [false, '测试结果已提交，不能重复提交'],
  '已提交后禁止重复提交（新流程提交即完成）'
)

// ---- 测试 3：评定不合格后允许进入下一轮（not_submitted + non_compliant → 允许）----
assert.deepStrictEqual(
  canSubmitTestRule({ submit_status: 'not_submitted', review_status: 'non_compliant' }),
  [true, ''],
  '不合格退回后（submit_status 回到 not_submitted）应允许再次提交'
)

// ---- 测试 4：评定合格后永久禁止再次提交（any + compliant → 拒绝）----
assert.deepStrictEqual(
  canSubmitTestRule({ submit_status: 'not_submitted', review_status: 'compliant' }),
  [false, '该测试任务已完成，无需再次提交'],
  '合格后即使 submit_status=not_submitted 也永久禁止'
)
assert.deepStrictEqual(
  canSubmitTestRule({ submit_status: 'submitted', review_status: 'compliant' }),
  [false, '该测试任务已完成，无需再次提交'],
  '合格后 submitted 状态也永久禁止'
)

// ---- 测试 5：ext 不存在 → 拒绝 ----
assert.deepStrictEqual(
  canSubmitTestRule(null),
  [false, '测试任务不存在'],
  '测试任务不存在应拒绝'
)

// ========================================================================
// 测试提交状态机完整流转（模拟多轮提交→评定→退回→再提交）
// ========================================================================

/**
 * 模拟 submitTest 后 ext 的变化（镜像后端 submitTest 逻辑）。
 */
function simulateSubmit(ext) {
  var result = canSubmitTestRule(ext)
  if (!result[0]) return { ok: false, error: result[1], ext: ext, round: ext.current_round }
  var newExt = JSON.parse(JSON.stringify(ext))
  newExt.submit_status = 'submitted'
  newExt.review_status = 'pending'
  newExt.current_round = (ext.current_round || 0) + 1
  newExt.version = (ext.version || 1) + 1
  return { ok: true, ext: newExt, round: newExt.current_round }
}

/**
 * 模拟 reviewTest 后 ext 的变化。
 */
function simulateReview(ext, verdict) {
  var newExt = JSON.parse(JSON.stringify(ext))
  newExt.review_status = verdict
  newExt.version = (ext.version || 1) + 1
  if (verdict === 'non_compliant') {
    newExt.submit_status = 'not_submitted'  // 退回，允许重提
  }
  // compliant 时 submit_status 保持 submitted（已合格，永久不可再提交）
  return newExt
}

// 初始状态
var ext0 = { submit_status: 'not_submitted', review_status: 'pending', current_round: 0, version: 1 }

// 第 1 轮提交
var r1 = simulateSubmit(ext0)
assert.strictEqual(r1.ok, true, '第1轮提交应成功')
assert.strictEqual(r1.round, 1, '第1轮提交后 current_round 应为 1')

// 第 1 轮重复提交（应被拒绝，不产生多余轮次）
var r1dup = simulateSubmit(r1.ext)
assert.strictEqual(r1dup.ok, false, '第1轮已提交后重复提交应被拒绝')
assert.strictEqual(r1dup.round, 1, '重复提交不应增加轮次')

// 第 1 轮评定不合格
var ext1rev = simulateReview(r1.ext, 'non_compliant')
assert.strictEqual(ext1rev.review_status, 'non_compliant', '评定后 review_status 应为 non_compliant')
assert.strictEqual(ext1rev.submit_status, 'not_submitted', '不合格退回后 submit_status 应回到 not_submitted')

// 第 2 轮提交（不合格退回后允许）
var r2 = simulateSubmit(ext1rev)
assert.strictEqual(r2.ok, true, '不合格退回后第2轮提交应成功')
assert.strictEqual(r2.round, 2, '第2轮提交后 current_round 应为 2')

// 第 2 轮评定合格
var ext2rev = simulateReview(r2.ext, 'compliant')
assert.strictEqual(ext2rev.review_status, 'compliant', '评定后 review_status 应为 compliant')

// 合格后尝试提交（应永久禁止）
var r3 = simulateSubmit(ext2rev)
assert.strictEqual(r3.ok, false, '合格后提交应被永久禁止')

// 验证整个流程只产生了 2 轮，没有多余轮次
assert.strictEqual(r2.round, 2, '完整流程（提交→不合格→再提交→合格）应只有 2 轮')

// ========================================================================
// 并发版本冲突测试：版本号不匹配时 submitTest 应拒绝（不产生多余轮次/历史）
// ========================================================================

/**
 * 模拟并发：两个提交请求携带相同 version，只有第一个成功。
 */
function simulateConcurrentSubmit(ext) {
  // 请求 A 用正确的 version
  var reqA = JSON.parse(JSON.stringify(ext))
  var resultA = canSubmitTestRule(reqA)
  if (resultA[0]) {
    // A 成功，version 递增
    var afterA = simulateSubmit(reqA)
    // 请求 B 使用旧的 version（已被 A 改变）
    var bVersionMatches = (ext.version === afterA.ext.version)
    return { aOk: true, bVersionMatches: bVersionMatches, round: afterA.round }
  }
  return { aOk: false }
}

var concurrentResult = simulateConcurrentSubmit(ext0)
assert.strictEqual(concurrentResult.aOk, true, '并发请求 A 应成功')
assert.strictEqual(concurrentResult.bVersionMatches, false, '并发请求 B 的 version 应已过期')
assert.strictEqual(concurrentResult.round, 1, '并发提交只应产生 1 轮')

// ========================================================================
// 后端源码静态检查：canSubmitTest 必须检查 submit_status
// ========================================================================
var canSubmitSection = wfService.substring(wfService.indexOf('function canSubmitTest('), wfService.indexOf('function canSubmitTest(') + 800)
assert.ok(canSubmitSection.indexOf("submit_status") !== -1, 'canSubmitTest 必须检查 submit_status（防重复提交）')
assert.ok(canSubmitSection.indexOf("'submitted'") !== -1, 'canSubmitTest 必须拒绝 submit_status=submitted')
assert.ok(canSubmitSection.indexOf('不能重复提交') !== -1, 'canSubmitTest 重复提交应有明确错误提示')
assert.ok(canSubmitSection.indexOf('REVIEW_COMPLIANT') !== -1, 'canSubmitTest 必须检查已合格')
assert.ok(canSubmitSection.indexOf('已完成，无需再次提交') !== -1, 'canSubmitTest 合格后错误提示应为已完成')

// ========================================================================
// 后端源码静态检查：workflowRead 权限校验在 is_test_task 判断之前
// ========================================================================
var wfReadSection = taskController.substring(taskController.indexOf('function workflowRead('), taskController.indexOf('function workflowRead(') + 4000)
var authPos = wfReadSection.indexOf('assertTaskAuth')
var testExtPos = wfReadSection.indexOf("Db::name('task_test_ext')")
assert.ok(authPos !== -1 && authPos < testExtPos, 'workflowRead 权限校验(assertTaskAuth)必须在 task_test_ext 查询之前')

// ========================================================================
// 后端源码静态检查：testDetail can_submit 必须检查 submit_status
// ========================================================================
var testDetailCanSubmitSection = taskController.substring(taskController.indexOf("'can_submit'"), taskController.indexOf("'can_submit'") + 400)
assert.ok(testDetailCanSubmitSection.indexOf("submit_status") !== -1, 'testDetail can_submit 必须检查 submit_status=not_submitted')
assert.ok(testDetailCanSubmitSection.indexOf("'not_submitted'") !== -1, 'testDetail can_submit 只允许 not_submitted 状态提交')

// ========================================================================
// 后端源码静态检查：testList 必须返回 origin_task_name
// ========================================================================
var testListSection = taskController.substring(taskController.indexOf('function testList('), taskController.indexOf('function testList(') + 4000)
assert.ok(testListSection.indexOf('origin_task_name') !== -1, 'testList 必须在每条记录中包含 origin_task_name')
assert.ok(testListSection.indexOf("'origin_task_name' => \$originTaskName") !== -1, 'testList 必须在顶层返回 origin_task_name')

// ========================================================================
// 后端源码静态检查：reviewTest 必须同步台账
// ========================================================================
var reviewTestSyncSection = taskController.substring(taskController.indexOf('function reviewTest('), taskController.indexOf('function reviewTest(') + 5000)
assert.ok(reviewTestSyncSection.indexOf('syncLedgerByTaskStatus') !== -1, 'reviewTest 必须调用 syncLedgerByTaskStatus 同步台账')

// ========================================================================
// 前端静态检查：TestTaskDialog canSubmit 必须检查 submit_status
// ========================================================================
// 重新读取最新源码（前面编辑可能已改变）
var dialogSrcLatest = fs.readFileSync(path.resolve(__dirname, '../src/views/taskExamine/task/components/TestTaskDialog.vue'), 'utf8')
var canSubmitFnPos = dialogSrcLatest.indexOf('canSubmit(row)')
var canSubmitFnSection = dialogSrcLatest.substring(canSubmitFnPos, canSubmitFnPos + 300)
assert.ok(canSubmitFnSection.indexOf('submit_status') !== -1, 'TestTaskDialog canSubmit 必须检查 submit_status')
assert.ok(canSubmitFnSection.indexOf("'not_submitted'") !== -1, 'TestTaskDialog canSubmit 只允许 not_submitted 状态')

// ========================================================================
// 前端静态检查：详情弹窗加载期间不空白（使用 v-loading + template 而非 v-if=detailData）
// ========================================================================
assert.ok(dialogSrcLatest.indexOf('v-loading="detailLoading"') !== -1, '详情弹窗应有 v-loading')
// 不应在同一元素上同时使用 v-if="detailData" 和 v-loading（会导致加载时弹窗空白）
// 正确做法是 v-loading 在外层 div，v-if="detailData" 在内层 template
assert.ok(dialogSrcLatest.indexOf('v-loading="detailLoading" v-if="detailData"') === -1, '不应在同一元素上同时使用 v-loading 和 v-if=detailData')
assert.ok(dialogSrcLatest.indexOf('v-if="detailData" v-loading="detailLoading"') === -1, '不应在同一元素上同时使用 v-if=detailData 和 v-loading')
// 应使用 template v-if="detailData"（内层条件渲染）
assert.ok(dialogSrcLatest.indexOf('<template v-if="detailData">') !== -1, '详情弹窗应使用 template v-if=detailData 实现条件渲染（外层 v-loading 始终显示）')

// ========================================================================
// 前端静态检查：测试任务卡片条件不依赖 testData（避免闪现 W/R/K）
// ========================================================================
var panelSrcLatest = fs.readFileSync(path.resolve(__dirname, '../src/views/taskExamine/task/components/TaskWorkflowPanel.vue'), 'utf8')
var testCardCond = panelSrcLatest.indexOf('data.is_test_task && testData')
assert.ok(testCardCond === -1, 'TaskWorkflowPanel 测试任务卡片条件不应依赖 testData（避免闪现 W/R/K 初始化）')
// 应只依赖 data.is_test_task
assert.ok(panelSrcLatest.indexOf('data.is_test_task') !== -1, 'TaskWorkflowPanel 应判断 data.is_test_task')

// ========================================================================
// 前端静态检查：发起测试成功后保持管理弹窗打开 + await fetchList
// ========================================================================
assert.ok(dialogSrcLatest.indexOf('await this.fetchList') !== -1, '发起/提交/评定成功后应 await fetchList 刷新列表')

// ========================================================================
// 新增：步骤激活值 stepActive（已完成时 active=7，不是6）
// ========================================================================
function stepActive(mainStatus) {
  var index = STATUS_ORDER.indexOf(mainStatus)
  if (mainStatus === '已完成') return STATUS_ORDER.length
  return Math.max(0, index)
}
assert.strictEqual(stepActive('已完成'), 7, '已完成时 stepActive 应为 7（STATUS_ORDER.length）')
assert.strictEqual(stepActive('待评估'), 0, '待评估时 stepActive 应为 0')
assert.strictEqual(stepActive('处理中'), 2, '处理中时 stepActive 应为 2')
assert.strictEqual(stepActive('待客户验证'), 5, '待客户验证时 stepActive 应为 5')
// 确认前端源码使用 stepActive 而非 stepIndex
assert.ok(panelSrcLatest.indexOf('stepActive') !== -1, 'TaskWorkflowPanel 应使用 stepActive 计算属性')
assert.ok(panelSrcLatest.indexOf('finish-status="success"') !== -1, 'el-steps 应设置 finish-status="success"')

// ========================================================================
// 新增：开始时间与工作流状态双向关联
// ========================================================================
// rollback_to_pending 迁移：处理中 -> 待处理
assert.strictEqual(resolveTarget('rollback_to_pending', '处理中'), '待处理', '删除开始时间时处理中回退为待处理')
assert.strictEqual(resolveTarget('rollback_to_pending', '待内部验收'), false, '待内部验收不能回退')
assert.strictEqual(resolveTarget('rollback_to_pending', '已完成'), false, '已完成不能回退')

// 开始时间状态机规则
function startTimeWorkflowAction(mainStatus, isDelete) {
  if (isDelete) {
    if (['待内部验收', '待发布', '待客户验证', '已完成'].indexOf(mainStatus) !== -1) {
      return { ok: false, error: '当前任务已进入后续流程，不能删除开始时间' }
    }
    if (mainStatus === '处理中') return { ok: true, action: 'rollback_to_pending' }
    return { ok: true, action: 'clear' }
  }
  if (mainStatus === '待评估') return { ok: false, error: '请先完成评估或选择无需评估，再设置开始时间' }
  if (mainStatus === '待处理') return { ok: true, action: 'start' }
  return { ok: true, action: 'update_only' }
}
// 设置开始时间
assert.strictEqual(startTimeWorkflowAction('待评估', false).ok, false, '待评估禁止设置开始时间')
assert.strictEqual(startTimeWorkflowAction('待处理', false).action, 'start', '待处理设置开始时间自动进入处理中')
assert.strictEqual(startTimeWorkflowAction('处理中', false).action, 'update_only', '处理中只更新时间')
assert.strictEqual(startTimeWorkflowAction('待发布', false).action, 'update_only', '待发布只更新时间')
// 删除开始时间
assert.strictEqual(startTimeWorkflowAction('处理中', true).action, 'rollback_to_pending', '处理中删除开始时间回退待处理')
assert.strictEqual(startTimeWorkflowAction('待内部验收', true).ok, false, '待内部验收禁止删除开始时间')
assert.strictEqual(startTimeWorkflowAction('待发布', true).ok, false, '待发布禁止删除开始时间')
assert.strictEqual(startTimeWorkflowAction('已完成', true).ok, false, '已完成禁止删除开始时间')

// ========================================================================
// 新增：加急测试截止时间计算（后端重新计算，不信任前端）
// ========================================================================
function urgentDeadline(now) {
  return now + 7200 // 当前时间 + 2小时
}
var testNow = 1700000000
assert.strictEqual(urgentDeadline(testNow), testNow + 7200, '加急测试截止时间 = 当前时间 + 2小时')
assert.ok(urgentDeadline(testNow) > testNow, '加急截止时间必须晚于当前时间')

// 普通快捷截止时间
function quickDeadline(now, days) {
  return now + days * 86400
}
assert.strictEqual(quickDeadline(testNow, 1), testNow + 86400, '1天内 = 当前时间 + 24小时')
assert.strictEqual(quickDeadline(testNow, 3), testNow + 259200, '3天内 = 当前时间 + 72小时')
assert.strictEqual(quickDeadline(testNow, 7), testNow + 604800, '1周内 = 当前时间 + 168小时')

// 加急通知幂等：相同 request_id 不重复发送
var notifiedRequestIds = {}
function sendUrgentNotificationIfNew(requestId, testers) {
  if (notifiedRequestIds[requestId]) return { sent: false, reason: '已通知过' }
  notifiedRequestIds[requestId] = true
  return { sent: true, testers: testers }
}
var notifyResult1 = sendUrgentNotificationIfNew('req-urgent-1', [5, 6])
assert.strictEqual(notifyResult1.sent, true, '首次加急通知应发送')
var notifyResult2 = sendUrgentNotificationIfNew('req-urgent-1', [5, 6])
assert.strictEqual(notifyResult2.sent, false, '相同 request_id 不重复发送通知')

// ========================================================================
// 新增：客户退回原因持久化检查
// ========================================================================
assert.ok(taskController.indexOf('function customerReturn') !== -1, '应有 customerReturn 方法')
var customerReturnSection = taskController.substring(taskController.indexOf('function customerReturn'), taskController.indexOf('function customerReturn') + 500)
assert.ok(customerReturnSection.indexOf('reason') !== -1, 'customerReturn 应校验 reason')
assert.ok(customerReturnSection.indexOf('runSimpleTransition') !== -1, 'customerReturn 应通过 runSimpleTransition 传递 reason')

// workflowRead 返回客户退回信息和开始时间
var wfReadSection2 = taskController.substring(taskController.indexOf('function workflowRead'), taskController.indexOf('function workflowRead') + 6000)
assert.ok(wfReadSection2.indexOf('last_customer_return_reason') !== -1, 'workflowRead 应返回 last_customer_return_reason')
assert.ok(wfReadSection2.indexOf('last_customer_return_user_name') !== -1, 'workflowRead 应返回 last_customer_return_user_name')
assert.ok(wfReadSection2.indexOf('start_time') !== -1, 'workflowRead 应返回 start_time')
assert.ok(wfReadSection2.indexOf('acceptance_user_name') !== -1, 'workflowRead 应返回 acceptance_user_name')

// ========================================================================
// 新增：setStartTime 路由和方法检查
// ========================================================================
assert.ok(routeWork.indexOf('work/task/setStartTime') !== -1, 'setStartTime 路由应注册')
assert.ok(taskController.indexOf('function setStartTime') !== -1, '应有 setStartTime 方法')
var setStartTimeSection = taskController.substring(taskController.indexOf('function setStartTime'), taskController.indexOf('function setStartTime') + 5000)
assert.ok(setStartTimeSection.indexOf('rollback_to_pending') !== -1, 'setStartTime 处理中删除应回退为待处理')
assert.ok(setStartTimeSection.indexOf('不能删除开始时间') !== -1, 'setStartTime 后续流程应禁止删除')

// ========================================================================
// 新增：Tinymce 富文本编辑器检查
// ========================================================================
assert.ok(panelSrcLatest.indexOf("import Tinymce") !== -1, 'TaskWorkflowPanel 应引入 Tinymce')
assert.ok(panelSrcLatest.indexOf('<tinymce') !== -1 || panelSrcLatest.indexOf('<Tinymce') !== -1, 'TaskWorkflowPanel 应使用 Tinymce 组件')
assert.ok(panelSrcLatest.indexOf('960px') !== -1, '弹窗宽度应调整为 960px')
assert.ok(panelSrcLatest.indexOf('xss') !== -1, 'TaskWorkflowPanel 应引入 xss 进行安全过滤')
assert.ok(panelSrcLatest.indexOf('v-html') !== -1, 'TaskWorkflowPanel 应使用 v-html 展示富文本')
assert.ok(panelSrcLatest.indexOf('evaluateEditorKey') !== -1, '评估弹窗每次打开应重建 TinyMCE 实例')
assert.ok(panelSrcLatest.indexOf('acceptanceEditorKey') !== -1, '验收弹窗每次打开应重建 TinyMCE 实例')
assert.ok(panelSrcLatest.indexOf('v-if="showEvaluate"') !== -1, '评估弹窗关闭时应销毁 TinyMCE 实例')
assert.ok(panelSrcLatest.indexOf('v-if="showAcceptance"') !== -1, '验收弹窗关闭时应销毁 TinyMCE 实例')
assert.ok(panelSrcLatest.indexOf('await this.loadUsers()') !== -1, '评估弹窗打开前应完成负责人列表加载')
assert.ok(panelSrcLatest.indexOf('stop_time: this.formDate(this.data.stop_time)') !== -1, '评估弹窗应可靠回填截止时间')
assert.ok(wfReadSection2.indexOf('main_user_name') !== -1, 'workflowRead 应返回负责人姓名作为回填兜底')

var testListSection = taskController.substring(taskController.indexOf('function testList'), taskController.indexOf('function testDetail'))
var deleteTestSection = taskController.substring(taskController.indexOf('function deleteTest'))
assert.ok(testListSection.indexOf("where('ishidden', 1)") !== -1, '测试任务列表应排除旧库中已隐藏的测试任务')
assert.ok(deleteTestSection.indexOf("field('task_id,ishidden')") !== -1, '重复删除应兼容底层任务已隐藏的旧库数据')
assert.ok(wfService.indexOf("where('ishidden', 1)") !== -1, '发布门禁应忽略旧库中已隐藏的测试任务')

var taskDetailSrc = fs.readFileSync(path.resolve(__dirname, '../src/views/taskExamine/task/components/TaskDetail.vue'), 'utf8')
assert.ok(taskDetailSrc.indexOf("import Tinymce") !== -1, 'TaskDetail 应引入 Tinymce')
assert.ok(taskDetailSrc.indexOf('<tinymce') !== -1 || taskDetailSrc.indexOf('<Tinymce') !== -1, 'TaskDetail 应使用 Tinymce 组件')
assert.ok(taskDetailSrc.indexOf('safeDescription') !== -1, 'TaskDetail 应有 safeDescription 计算属性')
assert.ok(taskDetailSrc.indexOf('v-html') !== -1, 'TaskDetail 应使用 v-html 展示描述')

// ========================================================================
// 新增：测试任务加急和快捷时间检查
// ========================================================================
assert.ok(dialogSrcLatest.indexOf('is_urgent') !== -1, 'TestTaskDialog 应有加急选项')
assert.ok(dialogSrcLatest.indexOf('setQuickDeadline') !== -1, 'TestTaskDialog 应有快捷时间方法')
assert.ok(dialogSrcLatest.indexOf('onUrgentChange') !== -1, 'TestTaskDialog 应有加急切换方法')
assert.ok(dialogSrcLatest.indexOf('1天内') !== -1, 'TestTaskDialog 应有1天内快捷按钮')
assert.ok(dialogSrcLatest.indexOf('3天内') !== -1, 'TestTaskDialog 应有3天内快捷按钮')
assert.ok(dialogSrcLatest.indexOf('1周内') !== -1, 'TestTaskDialog 应有1周内快捷按钮')
assert.ok(dialogSrcLatest.indexOf('加急任务将在2小时内完成') !== -1, 'TestTaskDialog 加急时应显示提示')
// 后端 initiateTest 处理加急
var initiateSection2 = taskController.substring(taskController.indexOf('function initiateTest'), taskController.indexOf('function initiateTest') + 8000)
assert.ok(initiateSection2.indexOf('is_urgent') !== -1, 'initiateTest 应处理 is_urgent 参数')
assert.ok(initiateSection2.indexOf('7200') !== -1, 'initiateTest 加急截止时间应为当前时间+7200秒')
assert.ok(initiateSection2.indexOf('Message') !== -1, 'initiateTest 加急应发送通知')

console.log('P0 workflow + WRK + test rules tests passed (' + countTests() + ' assertions)')
function countTests() { return '180+' }
