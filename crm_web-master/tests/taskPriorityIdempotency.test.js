'use strict'

const fs = require('fs')
const path = require('path')

const root = path.resolve(__dirname, '..')
let count = 0

function check(condition, message) {
  if (!condition) {
    console.error('FAIL: ' + message)
    process.exit(1)
  }
  count++
}

const detail = fs.readFileSync(path.join(root, 'src/views/taskExamine/task/components/TaskDetail.vue'), 'utf8')
const workTask = fs.readFileSync(path.join(root, '../crm_php-master/application/work/controller/Task.php'), 'utf8')
const oaTask = fs.readFileSync(path.join(root, '../crm_php-master/application/oa/controller/Task.php'), 'utf8')

check(detail.includes('if (Number(value.id) === Number(def))'), '前端选择相同优先级时不应重复请求')
check(detail.includes('this.priorityVisible = false'), '相同优先级应正常关闭选择框')
for (const source of [workTask, oaTask]) {
  check(source.includes('(int)$taskInfo[\'priority\'] === $priorityId') || source.includes('(int)$dataInfo[\'priority\'] === $priorityId'), '后端应将相同优先级视为幂等成功')
  check(source.includes('in_array($priorityId, [0, 1, 2, 3], true)'), '后端应校验优先级范围')
  check(source.includes('$currentPriority'), '并发更新为相同优先级时仍应返回成功')
}

console.log('taskPriorityIdempotency.test.js: all ' + count + ' checks passed')
