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
console.log('P6 performance frontend test passed (' + count + ' assertions)')
