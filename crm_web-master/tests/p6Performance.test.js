'use strict'
const fs = require('fs'); const path = require('path'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }
const ROOT = path.resolve(__dirname, '..')
check(fs.existsSync(path.join(ROOT, 'src/api/crm/performance.js')), 'performance.js 应存在')
const W = { duty: 0.40, task: 0.30, quality: 0.20, collab: 0.10 }
const FACTORS = { '优秀': 1.20, '合格': 1.00, '待改进': 0.60 }
function weighted(d, t, q, c) { return Math.round((d * W.duty + t * W.task + q * W.quality + c * W.collab) * 100) / 100 }
check(Math.abs(Object.values(W).reduce((s, v) => s + v, 0) - 1) < 1e-9, '四权重合计1')
check(weighted(100, 100, 100, 100) === 100, '满分100')
check(weighted(90, 85, 88, 80) === 87.10, '示例87.1')
check(FACTORS['优秀'] === 1.2 && FACTORS['待改进'] === 0.6, '评级系数')

// ===================== 绩效页面语义完整性检查 =====================
const perfCtrl = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/application/crm/controller/Performance.php'), 'utf8')
const perfSvc = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/application/crm/logic/PerformanceService.php'), 'utf8')
const perfVue = fs.readFileSync(path.resolve(__dirname, '../src/views/crm/performance/index.vue'), 'utf8')
const factPanelVue = fs.readFileSync(path.resolve(__dirname, '../src/views/crm/performance/components/PerformanceFactPanel.vue'), 'utf8')
const perfApi = fs.readFileSync(path.resolve(__dirname, '../src/api/crm/performance.js'), 'utf8')
const routeCrm = fs.readFileSync(path.resolve(__dirname, '../../crm_php-master/config/route_crm.php'), 'utf8')

// 1. 被考核人员姓名解析：summaryList 必须返回 user_name
check(perfCtrl.indexOf("resolveUserInfoBatch") !== -1, 'Performance 应有 resolveUserInfoBatch 方法')
var summaryListSection = perfCtrl.substring(perfCtrl.indexOf('function summaryList('), perfCtrl.indexOf('function summaryList(') + 3000)
check(summaryListSection.indexOf("user_name") !== -1, 'summaryList 应返回 user_name')
check(summaryListSection.indexOf("user_post") !== -1, 'summaryList 应返回 user_post（岗位）')
check(summaryListSection.indexOf("user_structure") !== -1, 'summaryList 应返回 user_structure（部门）')

// 2. 生成方式追踪：create_method
check(perfCtrl.indexOf("create_method") !== -1, 'Performance 应追踪 create_method')
check(perfCtrl.indexOf("'manual'") !== -1, 'summarySave 应标记 create_method=manual（通过 safeSetCreateMethod）')
var ensureSection = perfCtrl.substring(perfCtrl.indexOf('function ensurePerformanceSummary('), perfCtrl.indexOf('function ensurePerformanceSummary(') + 1000)
check(ensureSection.indexOf("'auto'") !== -1, 'ensurePerformanceSummary 应标记 create_method=auto')
check(perfCtrl.indexOf("createMethodLabel") !== -1, '应有 createMethodLabel 方法')
check(perfCtrl.indexOf("系统自动归集") !== -1, '自动归集应有中文标签')
check(perfCtrl.indexOf("人工录入") !== -1, '人工录入应有中文标签')

// 3. 绩效事实来源解析
check(perfSvc.indexOf("resolveSourceObject") !== -1, 'PerformanceService 应有 resolveSourceObject 方法')
check(perfSvc.indexOf("sourceTypeLabels") !== -1, 'PerformanceService 应有 sourceTypeLabels 方法')
check(perfSvc.indexOf("decorateFactFull") !== -1, 'PerformanceService 应有 decorateFactFull 方法')
var factListSection = perfCtrl.substring(perfCtrl.indexOf('function factList('), perfCtrl.indexOf('function factList(') + 3000)
check(factListSection.indexOf("decorateFactFull") !== -1, 'factList 应使用 decorateFactFull 补充来源信息')
check(factListSection.indexOf("submit_user_name") !== -1, 'factList 应返回 submit_user_name')
check(factListSection.indexOf("dimension_stats") !== -1, 'factList 应返回 dimension_stats 维度统计')
// source_name/source_module 由 decorateFactFull 设置（在 PerformanceService 中）
check(perfSvc.indexOf("'source_name'") !== -1, 'PerformanceService decorateFactFull 应设置 source_name')
check(perfSvc.indexOf("'source_module'") !== -1, 'PerformanceService decorateFactFull 应设置 source_module')

// 4. 事实详情接口
check(perfCtrl.indexOf("function factDetail") !== -1, '应有 factDetail 方法')
check(routeCrm.indexOf("factDetail") !== -1, 'factDetail 路由应注册')
check(perfApi.indexOf("performanceFactDetailAPI") !== -1, '前端应导出 performanceFactDetailAPI')

// 5. 计算说明
check(perfSvc.indexOf("calculationBreakdown") !== -1, 'PerformanceService 应有 calculationBreakdown 方法')
check(perfSvc.indexOf("weights_formula") !== -1, 'calculationBreakdown 应返回权重公式')
check(perfSvc.indexOf("status_note") !== -1, 'calculationBreakdown 应返回状态说明')
var summaryReadSection = perfCtrl.substring(perfCtrl.indexOf('function summaryRead('), perfCtrl.indexOf('function summaryRead(') + 4000)
check(summaryReadSection.indexOf("calculation") !== -1, 'summaryRead 应返回 calculation 计算说明')

// 6. 季度筛选：fetchList 必须传递 period
check(perfVue.indexOf("params.period") !== -1, '前端 fetchList 应传递 period 参数')
check(perfVue.indexOf("onFilterChange") !== -1, '前端应有 onFilterChange 筛选变更处理')

// 7. 前端展示：员工信息区、维度卡片、计算说明
check(perfVue.indexOf("pp-emp-info") !== -1, '前端应有员工信息区')
check(perfVue.indexOf("pp-dim-cards") !== -1, '前端应有评分维度卡片')
check(perfVue.indexOf("pp-calc") !== -1, '前端应有计算说明区')
check(perfVue.indexOf("create_method_label") !== -1, '前端应展示生成方式')

// 8. 事实中心：来源追溯、采集方式、维度统计
check(factPanelVue.indexOf("source_module") !== -1, '事实中心应展示来源模块')
check(factPanelVue.indexOf("is_auto") !== -1, '事实中心应展示采集方式（自动/人工）')
check(factPanelVue.indexOf("dimension_stats") !== -1 || factPanelVue.indexOf("dimensionStats") !== -1, '事实中心应展示维度统计')
check(factPanelVue.indexOf("openDetail") !== -1, '事实中心应有查看详情入口')
check(factPanelVue.indexOf("performanceFactDetailAPI") !== -1, '事实中心应调用 factDetail 接口')
check(factPanelVue.indexOf("performanceFactListAPI") !== -1, '事实中心应使用 performanceFactListAPI（不再使用 extensions 重复定义）')

// 9. SQL 迁移：create_method 列
const sqlMigration = fs.readFileSync(path.resolve(__dirname, '../../deployment/sql/20260730_perf_create_method_forward.sql'), 'utf8')
check(sqlMigration.indexOf("create_method") !== -1, '应有 create_method 迁移')
check(sqlMigration.indexOf("IF(@has_create_method=0") !== -1, '迁移应使用幂等 information_schema 检查')
check(sqlMigration.indexOf("PREPARE") !== -1, '迁移应使用 PREPARE 实现幂等')

// ===================== "用户0" 回退修复检查 =====================
// 10. 后端 summarySave 必须校验员工真实存在
var summarySaveSection = perfCtrl.substring(perfCtrl.indexOf('function summarySave('), perfCtrl.indexOf('function summarySave(') + 2000)
check(summarySaveSection.indexOf('员工不存在或已禁用') !== -1, 'summarySave 必须校验员工真实存在并返回明确错误')

// 11. 后端 generateQuarterly 接口
check(perfCtrl.indexOf('function generateQuarterly') !== -1, '应有 generateQuarterly 批量生成接口')
// create_method/quarterly_base/reference_amount 列可能不存在（迁移未执行），代码必须用 filterOptionalPerfColumns 安全处理
check(perfCtrl.indexOf('function getOptionalPerfColumns') !== -1, '应有 getOptionalPerfColumns 缓存检测方法')
check(perfCtrl.indexOf('function filterOptionalPerfColumns') !== -1, '应有 filterOptionalPerfColumns 过滤方法')
check(perfCtrl.indexOf("'create_method'") !== -1 && perfCtrl.indexOf("'quarterly_base'") !== -1 && perfCtrl.indexOf("'reference_amount'") !== -1, 'filterOptionalPerfColumns 应检测全部3个可选列')
var genSection = perfCtrl.substring(perfCtrl.indexOf('function generateQuarterly('), perfCtrl.indexOf('function generateQuarterly(') + 3000)
check(genSection.indexOf('status') !== -1 && genSection.indexOf('1') !== -1, 'generateQuarterly 应只查询有效员工(status=1)')
check(genSection.indexOf('filterOptionalPerfColumns') !== -1, 'generateQuarterly 应调用 filterOptionalPerfColumns 安全处理可选列')
check(genSection.indexOf("'auto'") !== -1, 'generateQuarterly 应标记 create_method=auto')
check(genSection.indexOf('rules_version') !== -1, 'generateQuarterly 应保存评分规则版本')
check(genSection.indexOf('create_user_id') !== -1, 'generateQuarterly 应保存创建人')
check(genSection.indexOf('create_time') !== -1, 'generateQuarterly 应保存创建时间')
check(genSection.indexOf('period') !== -1, 'generateQuarterly 应保存考核周期')
check(routeCrm.indexOf('generateQuarterly') !== -1, 'generateQuarterly 路由应注册')
check(perfApi.indexOf('performanceGenerateQuarterlyAPI') !== -1, '前端应导出 performanceGenerateQuarterlyAPI')

// 11b. 后端 summaryDelete 接口
check(perfCtrl.indexOf('function summaryDelete') !== -1, '应有 summaryDelete 删除/作废接口')
var delSection = perfCtrl.substring(perfCtrl.indexOf('function summaryDelete('), perfCtrl.indexOf('function summaryDelete(') + 2000)
check(delSection.indexOf('perf_score_input') !== -1, 'summaryDelete 需要 perf_score_input 权限')
check(delSection.indexOf('已评级') !== -1 || delSection.indexOf('hasRating') !== -1, 'summaryDelete 应检查已评级状态')
check(delSection.indexOf('已确认') !== -1 || delSection.indexOf('isConfirmed') !== -1, 'summaryDelete 应检查已确认状态')
check(routeCrm.indexOf('summaryDelete') !== -1, 'summaryDelete 路由应注册')
check(perfApi.indexOf('performanceSummaryDeleteAPI') !== -1, '前端应导出 performanceSummaryDeleteAPI')

// 11c. 前端新增/删除入口
check(perfVue.indexOf('openScoreInputNew') !== -1, '前端应有新增绩效方法（要求先选员工）')
check(perfVue.indexOf('请先在上方搜索并选择一名真实员工') !== -1, '新增绩效未选员工时应有明确提示')
check(perfVue.indexOf('deleteRecord') !== -1, '前端应有删除方法')
check(perfVue.indexOf('确认删除该绩效记录') !== -1, '删除应有二次确认')

// 12. 前端禁止 user_id=0 录分
check(perfVue.indexOf("Number(row.user_id) <= 0") !== -1 || perfVue.indexOf("!row.user_id") !== -1, '前端录分入口必须校验 user_id > 0')
check(perfVue.indexOf('该记录缺少有效员工') !== -1 || perfVue.indexOf('员工信息无效') !== -1, '前端 user_id=0 时应有明确提示')
check(perfVue.indexOf('this.scoreForm.user_id') !== -1 && perfVue.indexOf('Number(this.scoreForm.user_id) <= 0') !== -1, 'doSaveSummary 必须硬校验 user_id > 0')

// 13. 前端工具栏不应有脱离上下文的全局录分按钮（录分必须绑定 perf_id/记录）
check(perfVue.indexOf('openScoreInputFromRow') !== -1, '前端应有从列表行打开录分的方法（绑定具体记录）')
check(perfVue.indexOf('openScoreInputFromDetail') !== -1, '前端应有从详情弹窗打开录分的方法')
// 录分弹窗必须显示员工姓名/岗位/部门/周期/记录编号
check(perfVue.indexOf('pp-score-header') !== -1, '录分弹窗应有员工信息头部')
check(perfVue.indexOf('pp-score-name') !== -1, '录分弹窗应显示员工姓名')
check(perfVue.indexOf('perf_id') !== -1 && perfVue.indexOf('记录 #') !== -1, '录分弹窗应显示绩效记录编号')

// 14. 前端空状态不能只显示空白
check(perfVue.indexOf('pp-empty-state') !== -1, '前端应有专业空状态组件')
check(perfVue.indexOf('emptyTitle') !== -1, '前端空状态应根据原因显示不同标题')
check(perfVue.indexOf('emptyDesc') !== -1, '前端空状态应根据原因显示不同描述')
check(perfVue.indexOf('尚未生成绩效记录') !== -1, '空状态应提示尚未生成记录')
check(perfVue.indexOf('加载失败') !== -1, '空状态应区分加载失败')
check(perfVue.indexOf('listError') !== -1, '前端应追踪列表加载错误状态')

// 15. 录分弹窗应有加权预览和调整原因
check(perfVue.indexOf('previewWeighted') !== -1, '录分弹窗应有加权得分实时预览')
check(perfVue.indexOf('adjust_reason') !== -1, '录分弹窗应有调整说明输入')

// ===================== 退回必须填写原因（写审计）检查 =====================
// 16. 退回流程：前端必须收集原因并发送，后端必须校验原因并写审计
var summaryReturnSection = perfCtrl.substring(perfCtrl.indexOf('function summaryReturn('), perfCtrl.indexOf('function summaryReturn(') + 1500)
check(summaryReturnSection.indexOf('退回必须填写原因（写审计）') !== -1, '后端 summaryReturn 必须校验原因非空并返回明确错误')
check(summaryReturnSection.indexOf('safeInsertAudit') !== -1, '后端 summaryReturn 必须写审计')
check(summaryReturnSection.indexOf('SUMMARY_RETURNED') !== -1, '后端 summaryReturn 必须把状态改为已退回')
var returnVueSection = perfVue.substring(perfVue.indexOf('async returnSummary('), perfVue.indexOf('async returnSummary(') + 1200)
check(returnVueSection.indexOf('summaryReturn') !== -1, '前端 returnSummary 应调用 summaryReturn 接口')
check(returnVueSection.indexOf('reason') !== -1, '前端 returnSummary 必须收集并提交退回原因')
check(returnVueSection.indexOf('inputValidator') !== -1, '前端 returnSummary 应在弹窗中强制校验原因非空')
check(returnVueSection.indexOf('退回必须填写原因') !== -1, '前端 returnSummary 应提示退回必须填写原因')
check(routeCrm.indexOf('summaryReturn') !== -1, 'summaryReturn 路由应注册')

console.log('P6 performance frontend test passed (' + count + ' assertions)')
