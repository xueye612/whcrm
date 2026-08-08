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
const indexVue = fs.readFileSync(path.join(root, 'src/views/taskExamine/task/index.vue'), 'utf8')
const cellVue = fs.readFileSync(path.join(root, 'src/views/taskExamine/task/components/TaskCell.vue'), 'utf8')
const detailVue = fs.readFileSync(path.join(root, 'src/views/taskExamine/task/components/TaskDetail.vue'), 'utf8')
const oaTaskPhp = fs.readFileSync(path.resolve(root, '../crm_php-master/application/oa/controller/Task.php'), 'utf8')

check(indexVue.includes('<span class="heading-action">操作</span>'), 'task list exposes the detail action column')
check(indexVue.includes('class="task-empty"'), 'task list has an empty state')
check(indexVue.includes('width: 100%'), 'task page uses the full available content width')
check(!indexVue.includes('max-width: 1240px'), 'task page no longer has the narrow 1240px width limit')
check(cellVue.includes('class="detail-button"'), 'task row has an explicit detail action')
check(cellVue.includes('@click.stop="rowFun(data)"'), 'detail action opens the selected task without toggling the row')
check(cellVue.includes('finalW || this.data.final_w || this.data.initW || this.data.init_w'), 'task row displays final or initial W value')
check(cellVue.includes("|| 'W-'"), 'task row shows a W placeholder when evaluation is missing')
check(cellVue.includes('return !this.data.is_test_task'), 'test tasks remain excluded from W/R/K display')
check(indexVue.includes(':show-text="false"'), 'progress header remains on one compact line')
check(indexVue.includes('class="list-heading__columns"'), 'task list has aligned metadata column headings')
check(indexVue.includes('class="wk wk-task progress-icon"'), 'progress header uses a compact task icon instead of a large avatar')
check(cellVue.includes('grid-template-columns: 138px 72px 100px 52px 48px 58px'), 'task metadata uses compact stable grid columns')
check(cellVue.includes('<i class="el-icon-time" />截止 '), 'due-date copy has spacing and an icon')
check(cellVue.includes('@click.stop="rowFun(data)">详情</el-button>'), 'task row uses a compact detail action without a clipped arrow')
check(cellVue.includes('class="test-task-mark">不适用'), 'test tasks use an unambiguous W/R/K not-applicable label')
check(indexVue.includes('已显示全部 {{ list.length }} 条任务'), 'task list has a clear completed-list footer')
check(detailVue.includes('immediate: true'), 'task detail loads immediately for the initial id')
check(detailVue.includes('this.loadError = message'), 'task detail preserves non-permission load errors')
check(detailVue.includes('任务详情加载失败'), 'task detail renders a visible load failure state')
check(detailVue.includes('@click="getDetail"'), 'task detail supports retrying a failed request')
check(detailVue.includes('@refresh="handleWorkflowRefresh"'), 'workflow updates use the list synchronization handler')
check(detailVue.includes("type: 'workflow-refresh'"), 'evaluating W/R/K notifies the task list to reload the row')
check(oaTaskPhp.includes('task_id,init_w,init_r,init_k,final_w,final_r,final_k'), 'my-task API returns initial and final W/R/K values')
check(oaTaskPhp.includes("['is_test_task']"), 'my-task API distinguishes test tasks from unevaluated tasks')

console.log('taskDetailExperience.test.js: all ' + count + ' checks passed')
