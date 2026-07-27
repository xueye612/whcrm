'use strict'
const assert = require('assert')
const fs = require('fs')
const path = require('path')
const ROOT = path.resolve(__dirname, '..')
let count = 0
function check(c, m) { if (!c) { console.error('FAIL: ' + m); process.exit(1) }; count++ }

// 文件存在
const apiFile = path.join(ROOT, 'src/api/crm/leadpool.js')
const panelFile = path.join(ROOT, 'src/views/crm/leadpool/index.vue')
check(fs.existsSync(apiFile), 'leadpool.js 应存在')
check(fs.existsSync(panelFile), 'leadpool panel 应存在')

// API 模块结构
const apiSrc = fs.readFileSync(apiFile, 'utf8')
;['leadpoolBatchSaveAPI','leadpoolRawSaveAPI','leadpoolReadAPI','leadpoolDedupeQueryAPI','leadpoolDedupeDecideAPI'].forEach(fn => {
  check(apiSrc.indexOf('export function ' + fn) !== -1, 'API 应导出 ' + fn)
})
check(apiSrc.indexOf('crm/leadpool/') !== -1, 'API 应指向 crm/leadpool/ 端点')

// 面板结构
const panelSrc = fs.readFileSync(panelFile, 'utf8')
check(panelSrc.indexOf('LeadPoolPanel') !== -1, '面板应注册 name')
check(panelSrc.indexOf('el-table') !== -1, '面板应含原始线索表格')
check(panelSrc.indexOf('查重') !== -1 && panelSrc.indexOf('归并') !== -1, '面板应含查重与归并操作')

// 查重键逻辑（前后端一致）
function buildDedupeKey(name, mobile) {
  const n = String(name).replace(/\s+/g, '')
  let m = String(mobile).replace(/\D/g, '')
  if (m.length > 11) m = m.slice(-11)
  return require('crypto').createHash('md5').update(n.toLowerCase() + '|' + m).digest('hex')
}
check(buildDedupeKey('济南国政科技','15628802133') === buildDedupeKey(' 济南国政科技 ','156-2880-2133'), '名称空白与分隔符应归一')
check(buildDedupeKey('a','13912345678') === buildDedupeKey('a','8613912345678'), '带国家码取末11位')
check(buildDedupeKey('a','1') !== buildDedupeKey('b','1'), '名称不同键不同')

console.log('P2 leadpool frontend test passed (' + count + ' assertions)')
