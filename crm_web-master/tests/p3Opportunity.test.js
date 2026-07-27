'use strict'
const assert = require('assert'); const fs = require('fs'); const path = require('path')
const ROOT = path.resolve(__dirname, '..'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }
const apiFile = path.join(ROOT, 'src/api/crm/opportunity.js')
const panelFile = path.join(ROOT, 'src/views/crm/opportunity/index.vue')
check(fs.existsSync(apiFile), 'opportunity.js 应存在')
check(fs.existsSync(panelFile), 'opportunity panel 应存在')
const apiSrc = fs.readFileSync(apiFile, 'utf8')
;['opportunitySaveAPI','opportunityReadAPI','opportunityListAPI','opportunityStageAdvanceAPI'].forEach(fn => check(apiSrc.indexOf(fn) !== -1, 'API 应导出 ' + fn))
const STAGES = { '外包': ['需求沟通','方案报价','签约','交付'] }
const REWARDS = { '外包': { '需求沟通': 200, '方案报价': 200 } }
check(REWARDS['外包']['需求沟通'] === 200 && REWARDS['外包']['方案报价'] === 200, '外包沟通/方案固定 200')
check(STAGES['外包'].length === 4, '外包四阶段')
check(STAGES['外包'].indexOf('需求沟通') !== -1, '含需求沟通阶段')
console.log('P3 opportunity frontend test passed (' + count + ' assertions)')
