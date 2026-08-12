'use strict'
const fs = require('fs'); const path = require('path'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }

const ROOT = path.resolve(__dirname, '..')

// 1. 独立台账统计路由已移除
const crmRouter = fs.readFileSync(path.join(ROOT, 'src/router/modules/crm.js'), 'utf8')
check(!crmRouter.includes("path: 'ledger/statistics'"), '独立台账统计路由已移除')
check(!crmRouter.includes('台账统计'), '台账统计菜单标题已移除')

// 2. statistics.vue 已改造为内嵌组件
const statsVue = fs.readFileSync(path.join(ROOT, 'src/views/crm/ledger/statistics.vue'), 'utf8')
check(statsVue.includes('LedgerStatisticsPanel'), '组件名改为 LedgerStatisticsPanel')
check(statsVue.includes('props:'), '组件有 props 接收日期')
check(statsVue.includes('startDate'), '接收 startDate prop')
check(statsVue.includes('endDate'), '接收 endDate prop')
check(!statsVue.includes('min-height:100vh'), '移除全屏页面样式')
check(!statsVue.includes('stats-toolbar'), '移除独立日期筛选工具栏')
check(statsVue.includes('el-collapse'), '使用 el-collapse 展开详细统计')
check(statsVue.includes('refresh()'), '有 refresh 方法供父组件调用')
check(!statsVue.includes("emitFilter('待验证')"), '统计卡片不再提供待验证状态')
check(statsVue.includes('lsp-inline-metrics'), '辅助指标使用紧凑单行布局')
check(statsVue.includes('flex-wrap: nowrap'), '辅助指标卡片始终保持单行')
check(statsVue.includes('lsp-inline-metric--open'), '未结台账使用独立卡片')
check(statsVue.includes('lsp-inline-metric--overdue'), '逾期台账使用独立卡片')
check(statsVue.includes('lsp-inline-metric--converted'), '任务转化使用独立卡片')
check(statsVue.includes('lsp-inline-metric--avg'), '平均处理时长使用独立卡片')
check(statsVue.includes('未结台账'), '辅助指标展示未结台账')
check(statsVue.includes('逾期台账'), '辅助指标展示逾期台账')
check(statsVue.includes('任务转化'), '展示任务转化数量和比例')
check(statsVue.includes('平均处理时长'), '展示平均处理时长')
check(statsVue.includes('openCount()'), '未结台账由未完成状态汇总')
check(statsVue.includes('.lsp-collapse-title { padding-left: 8px;'), '详细统计标题应与下方内容对齐')

// 3. ledger/index.vue 已引入统计面板
const indexVue = fs.readFileSync(path.join(ROOT, 'src/views/crm/ledger/index.vue'), 'utf8')
check(indexVue.includes('LedgerStatisticsPanel'), '台账页面引入 LedgerStatisticsPanel')
check(indexVue.includes('ledger-statistics-panel'), '模板中使用 ledger-statistics-panel')
check(indexVue.includes('statsStartDate'), '有 statsStartDate computed')
check(indexVue.includes('statsEndDate'), '有 statsEndDate computed')
check(indexVue.includes("ref=\"ledgerStats\""), '有 ref=ledgerStats')

// 4. 后端 statistics 方法不使用带冒号的绑定键（HY093 修复）
const phpRoot = path.resolve(__dirname, '../../crm_php-master')
const ledgerCtrl = fs.readFileSync(path.join(phpRoot, 'application/ledger/controller/Ledger.php'), 'utf8')
const statsMethod = ledgerCtrl.substring(ledgerCtrl.indexOf('public function statistics()'))
check(!statsMethod.includes("':st'"), '不再使用 :st 绑定键')
check(!statsMethod.includes("':overdue_threshold1'"), '不再使用 :overdue_threshold1 绑定键')
check(!statsMethod.includes('Db::query('), '不再使用 Db::query 手工拼接 SQL')
check(statsMethod.includes('Db::name('), '使用 ThinkPHP Query Builder')
check(statsMethod.includes('applyDataScopePublic'), '应用台账数据权限')
check(statsMethod.includes('SUM(CASE WHEN'), '使用条件聚合减少查询次数')
check(statsMethod.includes("->where('u.status', 1)"), '按负责人统计不显示已禁用人员')
check(statsMethod.includes('feedback_time'), '日期筛选使用 feedback_time')
check(statsMethod.includes('register_time'), '兼容 register_time fallback')
check(statsMethod.includes('台账统计查询失败'), '有中文异常提示')
check(statsMethod.includes('think\\Log'), '记录异常到后端日志')
const allowedStatusLines = ledgerCtrl.match(/\$allowedStatus\s*=\s*\[[^\]]+\]/g) || []
check(allowedStatusLines.length >= 2, '新增和编辑均校验台账状态')
check(allowedStatusLines.every(line => !line.includes('待验证')), '后端不再允许写入待验证状态')
check(statsMethod.includes("status IN ('待处理','待验证')"), '迁移前旧状态暂归入待处理统计')

// 5. 模型有公共数据权限方法
const ledgerModel = fs.readFileSync(path.join(phpRoot, 'application/ledger/model/CustomerLedger.php'), 'utf8')
check(ledgerModel.includes('applyDataScopePublic'), '模型有 applyDataScopePublic 方法')

// 6. ledger_extensions.js 有统计 API
const extJs = fs.readFileSync(path.join(ROOT, 'src/api/ledger_extensions.js'), 'utf8')
check(extJs.includes('ledgerStatisticsAPI'), '有 ledgerStatisticsAPI')

// 7. 历史状态迁移脚本
const migrationSql = fs.readFileSync(path.join(ROOT, '../deployment/sql/20260731_ledger_status_pending_forward.sql'), 'utf8')
check(migrationSql.includes("SET `status` = '待处理'"), '历史待验证状态迁移为待处理')
check(migrationSql.includes("WHERE `status` = '待验证'"), '迁移仅处理旧状态')

// 8. 数据质量检查链路和诊断规则
const ledgerRoutes = fs.readFileSync(path.join(phpRoot, 'config/route_ledger.php'), 'utf8')
const qualityMethod = ledgerCtrl.substring(
  ledgerCtrl.indexOf('public function qualityCheck()'),
  ledgerCtrl.indexOf('public function statistics()')
)
check(indexVue.includes('@click="showQualityCheck"'), '质量检查按钮已绑定处理方法')
check(indexVue.includes('ledgerQualityCheckAPI({})'), '质量检查按钮调用后端 API')
check(indexVue.includes('openQualityLedger'), '质量检查结果可打开对应台账')
check(extJs.includes('ledgerQualityCheckAPI'), '前端定义质量检查 API')
check(ledgerRoutes.includes('ledger/ledger/qualityCheck'), '后端注册质量检查路由')
check(qualityMethod.includes('无效任务关联'), '检查无效任务关联')
check(qualityMethod.includes('疑似重复台账'), '检查疑似重复台账')
check(qualityMethod.includes('描述为空'), '检查描述为空')
check(qualityMethod.includes('已完成无完成时间'), '检查已完成无完成时间')
check(qualityMethod.includes('完成时间异常'), '检查完成时间早于登记时间')
check(qualityMethod.includes('applyDataScopePublic'), '质量检查应用台账数据权限')
check(qualityMethod.includes('仅用于诊断，不自动修改数据'), '质量检查不会自动修改数据')

console.log('台账统计入口和 HY093 修复测试通过 (' + count + ' assertions)')
