'use strict'

const fs = require('fs')
const path = require('path')

let count = 0
function check(condition, message) {
  if (!condition) {
    console.error('FAIL: ' + message)
    process.exit(1)
  }
  count++
}

const root = path.resolve(__dirname, '..')
const workflowPanel = fs.readFileSync(path.join(root, 'src/views/taskExamine/task/components/TaskWorkflowPanel.vue'), 'utf8')
const projectWorkbench = fs.readFileSync(path.join(root, 'src/views/pm/task/index.vue'), 'utf8')
const ledgerView = fs.readFileSync(path.join(root, 'src/views/crm/ledger/index.vue'), 'utf8')
const phpRoot = path.resolve(root, '../crm_php-master/application')
const oaTask = fs.readFileSync(path.join(phpRoot, 'oa/controller/Task.php'), 'utf8')
const workTask = fs.readFileSync(path.join(phpRoot, 'work/controller/Task.php'), 'utf8')
const taskModel = fs.readFileSync(path.join(phpRoot, 'work/model/Task.php'), 'utf8')
const ledgerController = fs.readFileSync(path.join(phpRoot, 'ledger/controller/Ledger.php'), 'utf8')
const ledgerRecordController = fs.readFileSync(path.join(phpRoot, 'ledger/controller/Record.php'), 'utf8')
const ledgerCompletion = fs.readFileSync(path.join(root, 'src/utils/ledgerCompletion.js'), 'utf8')

check(workflowPanel.includes('v-html="safeHtml(testData && testData.test_scope'), '测试任务提交弹窗应按净化后的富文本渲染')
check(projectWorkbench.includes('sort: 3'), '项目工作台默认按截止时间排序')
check(oaTask.includes('$status==5 || $status==6'), 'OA 任务列表应正确识别已完成筛选值')
check(oaTask.includes('CASE WHEN t.status = 5 THEN 1 ELSE 0 END ASC'), 'OA 任务列表应将已完成任务排在最后')
check(oaTask.includes('CASE WHEN t.stop_time = 0 THEN 1 ELSE 0 END ASC, t.stop_time ASC'), 'OA 任务列表应优先显示最早截止任务')
check(oaTask.includes("Db::name('work_user')->where('user_id'"), 'OA 全部任务应包含用户加入项目的任务')
check(oaTask.includes("$completeWhere['t.status'] = 5"), 'OA 完成数应复用当前查询条件')
check(workTask.includes("$memberWorkSql = $memberWorkIds ? ' or task.work_id in ("), '项目工作台应自动纳入新加入项目')
check(taskModel.includes('$dataCount = $countQuery->count()'), '项目工作台数量应在状态和 W/R/K 筛选后统计')
check(taskModel.includes('CASE WHEN task.status = 5 THEN 1 ELSE 0 END ASC'), '项目工作台应将已完成任务排在最后')
check(taskModel.includes('$query->orderRaw($order)->select()'), '项目工作台的 CASE 排序应使用 orderRaw，避免 CASE 被转义为字段名')
check(ledgerView.includes("=== '增项开发'"), '台账转任务前端应默认选择增项开发项目')
check(ledgerView.includes('main_user_id: row.handler_user_id ||'), '台账转任务前端应默认选择台账处理人')
check(ledgerController.includes("['name' => '增项开发', 'status' => 1]"), '台账转任务后端应提供默认项目兜底')
check(ledgerController.includes("$mainUserId = (int)($ledger['handler_user_id'] ?? 0)"), '台账转任务后端应提供处理人兜底')
check(ledgerController.includes("$taskDescription = $this->sanitizeRichText($ledger['description'] ?? '')"), '任务详情应直接继承台账详情富文本')
check(ledgerController.includes("'<p><strong>转换原因：</strong>'"), '任务详情应追加台账转换原因备注')
check(ledgerView.includes('needsWrkEvaluation(scope.row)'), '台账仅应为待评估任务显示 W/R/K 入口')
check(ledgerView.includes("row.task_main_status === '待评估'"), 'W/R/K 入口应依据真实工作流状态')
check(ledgerView.includes('已转任务 #{{ scope.row.task_id }}'), '台账列表应明确标识已转换任务')
check(ledgerView.includes('v-model="form.demo_extension_required"'), '台账完成表单应选择是否扩展到演示版本')
check(ledgerView.includes('demoExtensionText(detail.demo_extension_required)'), '台账详情应展示演示版本扩展选择')
check(ledgerController.includes('请选择是否需要扩展到演示版本'), '台账保存接口应校验演示版本扩展选择')
check(ledgerRecordController.includes("$updateData['demo_extension_required']"), '补充处理完成台账时应保存演示版本扩展选择')
check(ledgerCompletion.includes('next.demo_extension_required = null'), '台账离开完成状态时应清除演示版本扩展选择')

console.log('taskWorkbenchConsistency.test.js: all ' + count + ' checks passed')
