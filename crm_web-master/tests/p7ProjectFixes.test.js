'use strict'
/**
 * P7 项目模块四项缺陷修复回归测试（纯静态断言）
 *  1. 项目详情设置开始时间不再误报"必须提供有效版本号"（前端按需拉取工作流版本号）
 *  2. 项目任务看板/列表展示 W/R/K 区分
 *  3. 任务列表可修改参与者（后端 updateOwner 兼容字符串入参）
 *  4. 工作台当日任务与逾期区分；?task_id= 路由直达不再空白（SlideView appear）
 */
const fs = require('fs'); const path = require('path'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }
const ROOT = path.resolve(__dirname, '..')
const PHP = path.resolve(__dirname, '../../crm_php-master')

const taskDetail = fs.readFileSync(path.join(ROOT, 'src/views/taskExamine/task/components/TaskDetail.vue'), 'utf8')
const taskBoard = fs.readFileSync(path.join(ROOT, 'src/views/pm/project/components/TaskBoard.vue'), 'utf8')
const taskCell = fs.readFileSync(path.join(ROOT, 'src/views/taskExamine/task/components/TaskCell.vue'), 'utf8')
const workbench = fs.readFileSync(path.join(ROOT, 'src/views/pm/task/index.vue'), 'utf8')
const slideView = fs.readFileSync(path.join(ROOT, 'src/components/SlideView.vue'), 'utf8')
const taskPhp = fs.readFileSync(path.join(PHP, 'application/work/controller/Task.php'), 'utf8')

// === 1. 开始时间版本号：前端按需拉取 workflowReadAPI，避免 version=0 被后端拒绝 ===
check(taskDetail.indexOf('workflowReadAPI') !== -1, 'TaskDetail 应引入 workflowReadAPI')
var setStartTimeFn = taskDetail.substring(taskDetail.indexOf('setStartTime(val)'), taskDetail.indexOf('setStartTime(val)') + 2000)
check(setStartTimeFn.indexOf('workflowReadAPI({ task_id: this.id })') !== -1, 'setStartTime 在版本号缺失时应按需调用 workflowReadAPI')
check(setStartTimeFn.indexOf('params.version = version') !== -1, 'setStartTime 应将解析到的版本号写入请求参数')

// === 2. W/R/K 区分：看板与列表均应渲染 ===
check(taskBoard.indexOf('task-wr-compact') !== -1, 'TaskBoard 应渲染 W/R/K 标签')
check(taskBoard.indexOf('wrkTooltip(element)') !== -1, 'TaskBoard 应有 wrkTooltip 方法')
check(taskBoard.indexOf('element.initW || element.init_w') !== -1, 'TaskBoard 应读取 initW/init_w 字段')
check(taskCell.indexOf('task-wr-compact') !== -1, 'TaskCell 应渲染 W/R/K 标签')
check(taskCell.indexOf('wrkTooltip(data)') !== -1, 'TaskCell 应有 wrkTooltip 方法')
check(taskCell.indexOf('data.initW || data.init_w') !== -1, 'TaskCell 应读取 initW/init_w 字段')

// === 3. 参与者修改：后端 updateOwner 应用 stringToArray 兼容字符串入参 ===
var updateOwnerSection = taskPhp.substring(taskPhp.indexOf('function updateOwner'), taskPhp.indexOf('function updateOwner') + 1200)
check(updateOwnerSection.indexOf("stringToArray(\$param['owner_userids'])") !== -1, 'updateOwner 应使用 stringToArray 规范化 owner_userids 再遍历')
check(updateOwnerSection.indexOf("foreach (\$param['owner_userids']") === -1, 'updateOwner 不得直接 foreach 字符串入参 owner_userids')

// === 4a. 工作台当日任务区分：当天到期不算逾期 ===
check(workbench.indexOf('deadline-today') !== -1, '工作台应有当日任务样式 deadline-today')
check(workbench.indexOf('今日 ') !== -1, '工作台当日任务应显示"今日"')
check(workbench.indexOf('var isToday') !== -1, 'deadlineText 应区分当日任务（isToday）')
check(workbench.indexOf('当天到期不算逾期') !== -1, 'deadlineText 应有当日不逾期的注释说明')

// === 4b. ?task_id= 路由直达不再空白：SlideView 过渡加 appear，使 afterEnter 在首次渲染触发 ===
check(slideView.indexOf('appear') !== -1, 'SlideView 过渡应带 appear，确保路由直达时 afterEnter 触发并加载详情')

// === 5. 工作台任务拖动分组：draggable 必须绑定真实 taskList，否则拖拽改动无法持久化 ===
check(workbench.indexOf('v-for="(item, index) in taskList"') !== -1, '工作台列应直接遍历真实 taskList（而非过滤副本），保证拖拽双向绑定')
check(workbench.indexOf('displayTaskList') === -1, '工作台不应再使用 displayTaskList 过滤副本（会断开 draggable 与 taskList 的绑定）')
check(workbench.indexOf('v-show="showCompleted || !element.checked"') !== -1, '已完成任务应通过 v-show 隐藏（保留在数组中，避免拖拽索引错位）')
var moveEndFn = workbench.substring(workbench.indexOf('moveEndTask(evt)'), workbench.indexOf('moveEndTask(evt)') + 600)
check(moveEndFn.indexOf('this.taskList[fromTop]') !== -1, 'moveEndTask 应读取真实 taskList（拖拽后已被 vuedraggable 更新）')

console.log('p7ProjectFixes.test.js: all ' + count + ' checks passed')
