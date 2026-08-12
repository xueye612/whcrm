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
const navbar = fs.readFileSync(path.join(root, 'src/views/layout/components/Navbar.vue'), 'utf8')
const navManager = fs.readFileSync(path.join(root, 'src/views/layout/components/NavManager.vue'), 'utf8')
const crmRouter = fs.readFileSync(path.join(root, 'src/router/modules/crm.js'), 'utf8')
const pmRouter = fs.readFileSync(path.join(root, 'src/router/modules/pm.js'), 'utf8')
const examineRouter = fs.readFileSync(path.join(root, 'src/router/modules/taskExamine.js'), 'utf8')
const crmLayout = fs.readFileSync(path.join(root, 'src/views/layout/CRMLayout.vue'), 'utf8')
const sidebarItem = fs.readFileSync(path.join(root, 'src/views/layout/components/Sidebar/Item.vue'), 'utf8')

for (const source of [navbar, navManager]) {
  check(source.includes("title: '工作台'"), '顶部应用应包含独立工作台')
  check(source.includes("title: '客户经营'"), '客户模块应命名为客户经营')
  check(source.includes("title: '项目与任务'"), '项目模块应命名为项目与任务')
  check(source.includes("title: '审批办公'"), '审批模块应命名为审批办公')
  check(source.includes("title: '数据分析'"), 'BI 模块应命名为数据分析')
  check(source.includes("path: '/crm/customer'"), '客户经营应直达客户列表')
  check(source.includes("path: '/project/workbench'"), '项目与任务应直达我的任务')
  check(source.includes("path: '/taskExamine/examine-index/my'"), '审批办公应直达审批页面')
}

check(pmRouter.includes("title: '我的任务'"), '项目与任务左侧首项应为我的任务')
check(pmRouter.includes("title: '台账'"), '台账应进入项目与任务菜单')
check(pmRouter.includes("title: '项目列表'"), '项目列表命名应明确')
check(pmRouter.includes("title: '任务统计'"), '统计入口应命名为任务统计')
check(crmRouter.includes("activeMenu: '/project/ledger'"), '旧台账路由应保留并映射新菜单')
check(!crmRouter.includes("path: 'reward'"), '客户经营不应包含奖惩入口')
check(!crmRouter.includes("path: 'performance'"), '客户经营不应包含绩效入口')
check(pmRouter.includes("path: 'reward'"), '项目与任务应包含奖惩入口')
check(pmRouter.includes("meta: { title: '奖惩', icon: 'money' }"), '项目与任务应展示奖惩菜单')
check(pmRouter.includes("path: 'performance'"), '项目与任务应包含绩效入口')
check(pmRouter.includes("meta: { title: '绩效', icon: 'business-intelligence' }"), '绩效菜单应使用字体库中可正常显示的分析图标')
check(pmRouter.includes("meta: { title: '外包项目', icon: 'contract' }"), '外包项目应使用区别于普通项目的合同图标')
check(sidebarItem.includes('width: 18px;') && sidebarItem.includes('justify-content: center;'), '侧栏图标应统一宽度并居中')
check(crmLayout.includes("this.$route.path === '/crm/workbench'"), 'CRM 布局应区分工作台与客户经营顶部激活态')
check(examineRouter.match(/\.\.\.layout\(true\),\s*ignore: true/g).length >= 2, '审批办公侧栏应隐藏重复任务菜单')

console.log('navigationReorganization.test.js: all ' + count + ' checks passed')
