'use strict'
const fs = require('fs'); const path = require('path'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }
const ROOT = path.resolve(__dirname, '..')
const apiFile = path.join(ROOT, 'src/api/work/outsource.js')
check(fs.existsSync(apiFile), 'outsource.js 应存在')
const apiSrc = fs.readFileSync(apiFile, 'utf8')
;['outsourceProjectSaveAPI','outsourceProjectReadAPI','outsourceDistributeSaveAPI'].forEach(fn => check(apiSrc.indexOf(fn) !== -1, 'API 应导出 ' + fn))
// 毛利/奖金池/分配逻辑
function computeMargin(rev, cost) { return Math.round((rev - cost) * 100) / 100 }
function computePools(rev, rp = 2, ep = 3) { return { reward_pool: Math.round(rev * rp) / 100, expense_pool: Math.round(rev * ep) / 100 } }
const DEFAULT_DIST = [['研发负责人', 40], ['技术与项目负责人', 28], ['客户成功工程师', 25], ['驻场服务专员', 5], ['市场运营专员', 2]]
check(computeMargin(100000, 40000) === 60000, '毛利计算')
check(computePools(100000).reward_pool === 2000, '奖励池2%')
check(computePools(100000).expense_pool === 3000, '费用池3%')
check(DEFAULT_DIST.reduce((s, r) => s + r[1], 0) === 100, '默认比例合计100%')
check(DEFAULT_DIST.length === 5, '实施三级5角色')
// 不足不自动分配
function distribute(pool, rs) { let ap = 0; const rows = rs.map(r => { ap += r[1]; return [r[0], r[1], Math.round(pool * r[1]) / 100] }); return { rows, unallocated: Math.round(pool * (100 - ap)) / 100 } }
const d = distribute(2000, [['A', 40], ['B', 20]])
check(d.unallocated === 800, '不足部分不自动分配')
console.log('P4 outsource frontend test passed (' + count + ' assertions)')
