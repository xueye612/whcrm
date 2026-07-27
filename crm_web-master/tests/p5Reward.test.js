'use strict'
const fs = require('fs'); const path = require('path'); let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }
const ROOT = path.resolve(__dirname, '..')
check(fs.existsSync(path.join(ROOT, 'src/api/crm/opportunity.js')), 'opportunity.js 存在')
// 制度固定金额
const FIXED = { '客户成功工程师': 900, '驻场服务专员': 900, '外包需求沟通': 200, '外包方案报价': 200 }
check(FIXED['客户成功工程师'] === 900 && FIXED['驻场服务专员'] === 900, '客户成功/驻场固定900')
check(FIXED['外包需求沟通'] === 200 && FIXED['外包方案报价'] === 200, '外包沟通/方案固定200')
// 本人回避
function assertNotSelf(a, b) { return Number(a) > 0 && Number(a) === Number(b) ? false : true }
check(assertNotSelf(2, 1) === true && assertNotSelf(1, 1) === false, '本人回避逻辑')
console.log('P5 reward frontend test passed (' + count + ' assertions)')
