/**
 * 项目实施-绩效归集 共享纯函数（CommonJS，可同时被 webpack/Vue 与 Node 测试加载）。
 *
 * 包含首尾的周期天数计算，前后端共用同一语义。
 */

/**
 * 计算两个 yyyy-MM-dd 字符串之间包含首尾的天数。
 * 任一为空、非法、或结束早于开始返回 0。
 */
function periodDays(startStr, endStr) {
  if (!startStr || !endStr) return 0
  var s = new Date(startStr + 'T00:00:00Z').getTime()
  var e = new Date(endStr + 'T00:00:00Z').getTime()
  if (isNaN(s) || isNaN(e) || e < s) return 0
  return Math.round((e - s) / 86400000) + 1
}

function shouldAutoFillOnSiteDays(touched, days) {
  return !touched && Number(days) > 0
}

function isExactContributionDuplicate(rows, form) {
  var currentId = Number((form && form.contribution_id) || 0)
  return (rows || []).some(function(row) {
    return Number(row.contribution_id) !== currentId &&
      Number(row.user_id) === Number(form.user_id) &&
      String(row.contribution_role || '').trim() === String(form.contribution_role || '').trim() &&
      String(row.start_date || '') === String(form.start_time || '') &&
      String(row.end_date || '') === String(form.end_time || '')
  })
}

function aggregationHasProblems(stats) {
  stats = stats || {}
  return Number(stats.project_conflicts || 0) > 0 || (Array.isArray(stats.project_errors) && stats.project_errors.length > 0)
}

module.exports = {
  periodDays: periodDays,
  shouldAutoFillOnSiteDays: shouldAutoFillOnSiteDays,
  isExactContributionDuplicate: isExactContributionDuplicate,
  aggregationHasProblems: aggregationHasProblems
}
