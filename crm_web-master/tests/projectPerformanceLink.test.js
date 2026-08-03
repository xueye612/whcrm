'use strict'
/**
 * 项目实施—绩效归集 链路 前端测试（纯 Node，源码结构 + 纯函数模拟）。
 *
 * 说明：本测试为【源码结构断言 + 纯函数模拟】，非 DOM/真实 Vue 组件测试。
 * 真实组件交互需在浏览器或 @vue/test-utils 环境验证（见交付报告）。
 *
 * 运行：node tests/projectPerformanceLink.test.js
 */
const fs = require('fs')
const path = require('path')

const ROOT = path.resolve(__dirname, '..')
let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }

const panelSrc = fs.readFileSync(path.join(ROOT, 'src/views/pm/project/components/ProjectImplementationPanel.vue'), 'utf8')

// ===== 里程碑负责人 =====
check(/responsible_user_id/.test(panelSrc), '里程碑弹窗应有负责人字段 responsible_user_id')
check(/请选择里程碑负责人|请选择负责人/.test(panelSrc), '负责人必填校验提示')
check(panelSrc.indexOf('负责人必须是当前项目成员') !== -1, '负责人必须是项目成员校验')
check(panelSrc.indexOf('负责人') !== -1 && panelSrc.indexOf('memberName(s.row.responsible_user_id)') !== -1, '里程碑表格展示负责人姓名')

// ===== 业务状态与绩效状态分列 =====
check(panelSrc.indexOf('业务状态') !== -1, '里程碑“业务状态”列（与绩效状态分开）')
check(panelSrc.indexOf('绩效状态') !== -1, '里程碑“绩效状态”列')
check(/contribTag\(s\.row\.status\)/.test(panelSrc), '贡献“状态”列使用 contribTag')
// 绩效状态不得由用户手工选择（无 el-select 绑定 performance_status）
check(!/v-model="[^"]*performance_status"/.test(panelSrc), '绩效状态不得由用户手工选择')

// ===== 五种绩效状态颜色映射（perfTag）=====
check(/perfTag\(s\.row\.performance_status\)/.test(panelSrc), '绩效状态使用 perfTag 颜色')
const perfMap = { 不计入: 'info', 待归集: 'warning', 待审核: '', 已通过: 'success', 已驳回: 'danger' }
Object.keys(perfMap).forEach(k => {
  check(panelSrc.indexOf(k) !== -1, '绩效状态映射包含：' + k)
})

// ===== 不计入/驳回原因 tooltip =====
check(/perfReason\(s\.row\)/.test(panelSrc), '绩效原因 tooltip 使用 perfReason')
check(/el-tooltip/.test(panelSrc), '使用 el-tooltip 展示原因')

// ===== 已通过记录禁止编辑/删除 =====
check((panelSrc.match(/:disabled="s\.row\.performance_status === '已通过'"/g) || []).length >= 4, '已通过绩效对应记录禁用编辑与删除（里程碑+贡献共 4 处）')

// ===== 周期天数与默认人日联动 =====
check(/contributionPeriod/.test(panelSrc), '应有 contributionPeriod 周期天数计算')
check(/周期 \{\{ contributionPeriod \}\} 天/.test(panelSrc), '展示“周期 N 天”')
check(/onSiteDaysTouched/.test(panelSrc), '应有用户手工修改人日标记 onSiteDaysTouched')
check(/autoFillOnSiteDays/.test(panelSrc), '应有日期联动默认人日 autoFillOnSiteDays')
check(/@change="onSiteDaysTouched = true"/.test(panelSrc), '用户修改人日后置位标记，避免被覆盖')

// ===== 贡献状态字段 =====
check(panelSrc.indexOf('已确认') !== -1 && panelSrc.indexOf('草稿') !== -1 && panelSrc.indexOf('已作废') !== -1, '贡献状态三档：草稿/已确认/已作废')
check(/已确认贡献现场人日必须大于 0/.test(panelSrc), '已确认贡献人日 > 0 校验')

// ===== 纯函数：周期天数（直接 require 生产实现，禁止复制）=====
const { periodDays, shouldAutoFillOnSiteDays, isExactContributionDuplicate, aggregationHasProblems } = require(path.join(ROOT, 'src/utils/projectPerf.js'))
check(periodDays('2026-01-01', '2026-01-01') === 1, '同一天周期 1 天')
check(periodDays('2026-01-01', '2026-01-05') === 5, '1~5 号周期 5 天（含首尾）')
check(periodDays('', '2026-01-05') === 0, '缺开始 -> 0')
check(periodDays('2026-01-05', '2026-01-01') === 0, '倒序 -> 0')
check(periodDays('2026-02-28', '2026-03-01') === 2, '跨月周期 2 天')
check(shouldAutoFillOnSiteDays(false, 3) === true, '新建且未手工修改时允许自动带出人日')
check(shouldAutoFillOnSiteDays(true, 3) === false, '编辑历史人工人日时禁止自动覆盖')
check(isExactContributionDuplicate([{ contribution_id: 1, user_id: 2, contribution_role: '实施', start_date: '2026-01-01', end_date: '2026-01-03' }], { contribution_id: 0, user_id: 2, contribution_role: '实施', start_time: '2026-01-01', end_time: '2026-01-03' }) === true, '生产工具识别精确重复贡献')
check(aggregationHasProblems({ project_errors: [{ source: 'x' }] }) === true, '归集 errors 非空时识别为异常结果')
check(aggregationHasProblems({ project_conflicts: 1 }) === true, '归集 conflicts 非零时识别为异常结果')

// ===== 面板使用共享 utils 而非内联复制 =====
check(panelSrc.indexOf('@/utils/projectPerf') !== -1, '面板应 import 共享 periodDays')

// ===== 后端 ProjectPerformanceService 结构断言（生产代码文件存在）=====
const svcSrc = fs.readFileSync(path.join(__dirname, '..', '..', 'crm_php-master', 'application', 'work', 'logic', 'ProjectPerformanceService.php'), 'utf8')
check(/const\s+SRC_MILESTONE\s*=\s*'project_milestone'/.test(svcSrc), '后端来源类型 project_milestone')
check(/const\s+SRC_CONTRIBUTION\s*=\s*'project_contribution'/.test(svcSrc), '后端来源类型 project_contribution')
check(svcSrc.indexOf("'milestone:' .") !== -1 || svcSrc.indexOf('milestone:') !== -1, 'source_id 格式 milestone:{id}')
check(svcSrc.indexOf('contribution:') !== -1, 'source_id 格式 contribution:{id}')
check(svcSrc.indexOf('function syncMilestone') !== -1, '统一同步 syncMilestone')
check(svcSrc.indexOf('function syncContribution') !== -1, '统一同步 syncContribution')
check(svcSrc.indexOf('function resubmitMilestone') !== -1, '显式重新提交 resubmitMilestone')
check(svcSrc.indexOf('function resubmitContribution') !== -1, '显式重新提交 resubmitContribution')
check(svcSrc.indexOf('function prepareDeleteMilestone') !== -1, '删除前事务内事实撤销 prepareDeleteMilestone')
check(svcSrc.indexOf('function prepareDeleteContribution') !== -1, '删除前事务内事实撤销 prepareDeleteContribution')
check(/已通过.*不可变|不可变|绩效已通过/.test(svcSrc), '已通过事实不可变保护')
// 不得 delete performance_fact（标记为已驳回保留历史）
check(svcSrc.indexOf('->delete()') === -1, 'syncMilestone/syncContribution 不得 delete performance_fact')
// 已驳回事实不得自动恢复
check(svcSrc.indexOf('事实已驳回，需显式重新提交绩效') !== -1, '已驳回事实不自动恢复为待审核')
// 每次更新都 ensureSummary（perf_id 一致性）
check(/ensureSummary/.test(svcSrc), '每次同步都调用 ensureSummary')
// 并发冲突仅唯一键冲突才幂等
check(svcSrc.indexOf('isUniqueViolation') !== -1, '区分唯一键冲突与其他 SQL 异常')
// 批量归集返回 conflicts
check(/'conflicts'/.test(svcSrc), '批量归集返回 conflicts')

// 控制器事务包装结构断言
const workSrc = fs.readFileSync(path.join(__dirname, '..', '..', 'crm_php-master', 'application', 'work', 'controller', 'Work.php'), 'utf8')
const msSaveBlock = workSrc.substring(workSrc.indexOf('function milestoneSave()'), workSrc.indexOf('function milestoneDelete()'))
check(msSaveBlock.indexOf('Db::startTrans()') !== -1, 'milestoneSave 使用事务')
check(msSaveBlock.indexOf('Db::rollback()') !== -1, 'milestoneSave 失败回滚')
check(msSaveBlock.indexOf('Db::commit()') !== -1, 'milestoneSave 成功提交')
check(msSaveBlock.indexOf('if (!$sync[\'ok\'])') !== -1, 'sync 失败时回滚并返回 error')
// 删除前事实检查
const msDelBlock = workSrc.substring(workSrc.indexOf('function milestoneDelete()'), workSrc.indexOf('function contributionSave()'))
check(msDelBlock.indexOf('prepareDeleteMilestone') !== -1, 'milestoneDelete 删除前事实检查与撤销')
const ctDelBlock = workSrc.substring(workSrc.indexOf('function contributionDelete()'))
check(ctDelBlock.indexOf('prepareDeleteContribution') !== -1, 'contributionDelete 删除前事实检查与撤销')

// implementationRead 结构断言：按 source_id in 批量查询，非全表扫描
check(workSrc.indexOf("'source_id', 'in',") !== -1, 'implementationRead 按 source_id in 批量查询事实')
check(workSrc.indexOf('period_days') !== -1, 'implementationRead 返回独立 period_days 字段')
check(workSrc.indexOf('performance_review_note') !== -1, 'implementationRead 返回 performance_review_note')

// Performance.php 结构断言
const perfSrc = fs.readFileSync(path.join(__dirname, '..', '..', 'crm_php-master', 'application', 'crm', 'controller', 'Performance.php'), 'utf8')
check(perfSrc.indexOf("'project_scanned'") !== -1, '自动归集返回 project_scanned')
check(perfSrc.indexOf("'project_conflicts'") !== -1, '自动归集返回 project_conflicts')
check(perfSrc.indexOf("'project_errors'") !== -1, '自动归集返回 project_errors')
check(perfSrc.indexOf('outsource_skipped_with_reason') !== -1, 'outsource 明确跳过')
check(perfSrc.indexOf('PerformanceService::manualSourceId') !== -1, '手工幂等 source_id 使用生产稳定键方法')

// PerformanceService 结构断言
const perfSvcSrc = fs.readFileSync(path.join(__dirname, '..', '..', 'crm_php-master', 'application', 'crm', 'logic', 'PerformanceService.php'), 'utf8')
check(/'project_milestone'\s+=>\s+'项目里程碑'/.test(perfSvcSrc), 'sourceTypeLabels 增加 project_milestone')
check(/'project_contribution'\s+=>\s+'项目贡献'/.test(perfSvcSrc), 'sourceTypeLabels 增加 project_contribution')
check(perfSvcSrc.indexOf('source_route') !== -1, 'resolveSourceObject 返回 source_route')
check(perfSvcSrc.indexOf('source_anchor') !== -1, 'resolveSourceObject 返回 source_anchor')
check(perfSvcSrc.indexOf('/project-list/project/') !== -1, 'source_route 跳转到项目详情')
const factPanelSrc = fs.readFileSync(path.join(ROOT, 'src/views/crm/performance/components/PerformanceFactPanel.vue'), 'utf8')
check(factPanelSrc.indexOf('aggregationHasProblems') !== -1, '归集错误提示直接使用生产工具判断')
check(factPanelSrc.indexOf('查看项目') !== -1, '绩效事实列表与详情提供查看项目入口')
check(panelSrc.indexOf('applyDeepLink') !== -1 && panelSrc.indexOf('imp-source-highlight') !== -1, '项目页按 query 定位并高亮来源记录')

// 迁移文件结构断言
const depDir = path.join(__dirname, '..', '..', 'deployment', 'sql')
check(fs.existsSync(path.join(depDir, '20260803_project_performance_link_precheck.sql')), 'precheck 迁移文件存在')
check(fs.existsSync(path.join(depDir, '20260803_project_performance_link_forward.sql')), 'forward 迁移文件存在')
check(fs.existsSync(path.join(depDir, '20260803_project_performance_link_verify.sql')), 'verify 迁移文件存在')
check(fs.existsSync(path.join(depDir, '20260803_project_performance_link_rollback_notes.sql')), 'rollback 迁移文件存在')
const fwdSql = fs.readFileSync(path.join(depDir, '20260803_project_performance_link_forward.sql'), 'utf8')
check(fwdSql.indexOf('uk_fact_source') !== -1, 'forward 迁移含 performance_fact UNIQUE(source_type, source_id)')
const preSql = fs.readFileSync(path.join(depDir, '20260803_project_performance_link_precheck.sql'), 'utf8')
check(preSql.indexOf('responsible_user_id') === -1, 'precheck 不引用尚未创建的 responsible_user_id')

// ===== 奖金页面布局结构断言 =====
const indexSrc = fs.readFileSync(path.join(ROOT, 'src/views/pm/project/index.vue'), 'utf8')
// 比例输入框存在独立容器和百分号单位
check(indexSrc.indexOf('reward-ratio-input') !== -1, '比例输入框存在独立容器 reward-ratio-input')
check(indexSrc.indexOf('reward-ratio-unit') !== -1, '比例列存在百分号单位 reward-ratio-unit')
// 比例列宽度从 150px 扩大到 200px
check(/label="比例"\s+width="200"/.test(indexSrc), '比例列宽度为 200px（不再使用 150px）')
// 响应式双栏容器
check(indexSrc.indexOf('reward-dual') !== -1, '奖金页面存在响应式双栏容器 reward-dual')
// 奖金池概览卡片
check(indexSrc.indexOf('reward-stat-card') !== -1 && indexSrc.indexOf('rsc-highlight') !== -1, '存在奖金池概览卡片（含高亮奖励池）')
// 合计状态颜色
check(indexSrc.indexOf('rrs-ok') !== -1 && indexSrc.indexOf('rrs-over') !== -1, '合计状态存在颜色区分（ok/over）')
// 发放节奏独立区域
check(indexSrc.indexOf('reward-payout-info') !== -1, '发放节奏使用独立说明区域')
// 空状态
check(indexSrc.indexOf('reward-empty-card') !== -1, '右侧无结果时显示空状态')

// ===== 实施档案五卡片结构断言 =====
check(panelSrc.indexOf('基本信息') !== -1 && panelSrc.indexOf('imp-card-title') !== -1, '实施档案拆分为标题卡片')
check(panelSrc.indexOf('项目周期') !== -1, '存在项目周期卡片标题')
check(panelSrc.indexOf('实施记录') !== -1, '存在实施记录卡片标题')
check(panelSrc.indexOf('验收信息') !== -1, '存在验收信息卡片标题')
check(panelSrc.indexOf('交付奖金规则') !== -1, '存在交付奖金规则卡片标题（从表单独立）')
// 保存按钮位于独立操作区域（不再紧贴验收下拉框）
check(panelSrc.indexOf('imp-card-actions') !== -1, '保存按钮位于独立操作区域 imp-card-actions')
// 不再使用 max-width:960px / max-width:600px 限制主要内容
check(!/max-width:\s*960px/.test(panelSrc), '实施档案不再使用 max-width:960px')
check(!/max-width:\s*600px/.test(indexSrc), '奖金页面不再使用 max-width:600px')
// 已通过 tooltip 保留
check(panelSrc.indexOf('绩效已审核通过') !== -1, '已通过 tooltip 保留')
// 重新提交保留
check(panelSrc.indexOf('重新提交') !== -1, '重新提交按钮保留')
// 深链高亮保留
check(panelSrc.indexOf('imp-source-highlight') !== -1, '深链高亮样式保留')
// 奖金规则表最大宽度限制为 720px
check(/max-width:\s*720px/.test(panelSrc), '奖金规则表宽度限制为 720px')
// 不再使用 max-width:480px 限制奖金规则表
check(!/max-width:\s*480px/.test(panelSrc), '奖金规则表不再使用 max-width:480px')
// el-input-number 不再使用 size="mini"（在比例列中）
check(!/size="mini"\s*\/><\/template>/.test(indexSrc.replace(/\s+/g, ' ')), '比例输入框不再使用 size=mini')

console.log('projectPerformanceLink test passed (' + count + ' assertions)')
