'use strict'
const fs = require('fs'); const path = require('path'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }
const ROOT = path.resolve(__dirname, '..')

// === 1. pm/task/index.vue reads task_id from route query ===
const taskVue = fs.readFileSync(path.join(ROOT, 'src/views/pm/task/index.vue'), 'utf8')
check(taskVue.includes("'$route.query.task_id'"), 'pm/task/index.vue watches $route.query.task_id')
check(taskVue.includes('openTaskFromRoute'), 'pm/task/index.vue has openTaskFromRoute method')
check(taskVue.includes('this.$route.query.task_id'), 'openTaskFromRoute reads task_id from route')
check(taskVue.includes('taskDetailShow = true'), 'sets taskDetailShow=true when task_id present')
check(taskVue.includes('task_iD = String'), 'sets task_iD from route param')

// === 2. closeBtn clears URL task_id ===
check(taskVue.includes("delete query.task_id"), 'closeBtn removes task_id from URL query')
check(taskVue.includes('$router.replace'), 'closeBtn uses $router.replace to update URL')

// === 3. TaskDetail component receives id prop ===
check(taskVue.includes(':id="task_iD"'), 'TaskDetail receives task_iD as id prop')
check(taskVue.includes('v-if="taskDetailShow"'), 'TaskDetail shown when taskDetailShow is true')

// === 4. ledger/index.vue "去评估 W/R/K" opens task detail ===
const ledgerVue = fs.readFileSync(path.join(ROOT, 'src/views/crm/ledger/index.vue'), 'utf8')
check(ledgerVue.includes('去评估 W/R/K'), 'ledger has "去评估 W/R/K" button')
check(ledgerVue.includes('task_id'), 'ledger opens task via task_id query param')
check(ledgerVue.includes('$router.resolve'), 'ledger uses $router.resolve for correct URL')
check(ledgerVue.includes('window.open'), 'ledger opens task in new tab')

// === 5. ledger/index.vue "查看任务" opens task detail ===
check(ledgerVue.includes('查看任务'), 'ledger has "查看任务" button')
check(ledgerVue.includes('openTaskLink'), 'ledger has openTaskLink method')

console.log('p6TaskIdRoute.test.js: all ' + count + ' checks passed')
